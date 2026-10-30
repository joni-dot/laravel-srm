<?php

namespace App\Http\Controllers;

use App\Enums\RiskLevel;
use App\Enums\SupplierStatus;
use App\Models\Certificate;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\SupplierSpend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every count here matches the supplier list the dashboard links to, so archived suppliers never count.
 */
class DashboardController extends Controller
{
    private const int UPCOMING_SHOWN = 10;

    private const int TOP_SPEND_SHOWN = 5;

    public function __invoke(Request $request): Response
    {
        $canViewContracts = $request->user()->can('viewDetails', Contract::class);
        $canViewSpend = $request->user()->can('viewAny', SupplierSpend::class);
        $spendYear = today()->year;

        return Inertia::render('Dashboard', [
            'attention' => [
                'contracts_action_soon' => $canViewContracts
                    ? $this->suppliers()->whereHas('contracts', fn (Builder $contracts) => $contracts->needingActionSoon())->count()
                    : null,
                'missing_certificates' => $this->suppliers()->missingRequiredCertificates()->count(),
                'certificates_expiring' => $this->activeSuppliers()->withExpiringCertificateNotReplaced()->count(),
                'high_risk' => $this->activeSuppliers()->withRiskLevel(RiskLevel::High)->count(),
                'rating_overdue' => $this->suppliers()->ratingOverdue()->count(),
            ],
            'upcoming' => $this->upcomingDeadlines($canViewContracts),
            'riskLevels' => array_map(fn (RiskLevel $level) => [
                'level' => $level,
                'count' => $this->suppliers()->withRiskLevel($level)->count(),
            ], [RiskLevel::High, RiskLevel::Medium, RiskLevel::Low]),
            'statuses' => $this->statusCounts(),
            'spendYear' => $spendYear,
            'topSpend' => $canViewSpend ? $this->topSpend($spendYear) : null,
        ]);
    }

    /**
     * @return Builder<Supplier>
     */
    private function suppliers(): Builder
    {
        return Supplier::query()->whereNull('archived_at');
    }

    /**
     * @return Builder<Supplier>
     */
    private function activeSuppliers(): Builder
    {
        return $this->suppliers()->where('status', SupplierStatus::Active);
    }

    /**
     * The soonest dates across contracts and certificates. Contracts are only read for people allowed to see them.
     *
     * @return list<array<string, mixed>>
     */
    private function upcomingDeadlines(bool $canViewContracts): array
    {
        $contracts = ! $canViewContracts ? collect() : Contract::query()
            ->needingActionSoon()
            ->whereHas('supplier', fn (Builder $supplier) => $supplier->whereNull('archived_at'))
            ->with('supplier:id,name')
            ->orderBy('action_by')
            ->limit(self::UPCOMING_SHOWN)
            ->get(['id', 'supplier_id', 'title', 'auto_renews', 'action_by'])
            ->map(fn (Contract $contract) => [
                'key' => "contract-{$contract->id}",
                'kind' => $contract->auto_renews ? 'contract_cancel_by' : 'contract_ends',
                'date' => $contract->action_by->toDateString(),
                'title' => $contract->title,
                'contract_id' => $contract->id,
                'supplier_id' => $contract->supplier_id,
                'supplier_name' => $contract->supplier->name,
            ]);

        $certificates = Certificate::query()
            ->expiringWithoutReplacement()
            ->where('expires_on', '>=', today())
            ->whereHas('supplier', fn (Builder $supplier) => $supplier->whereNull('archived_at')->where('status', SupplierStatus::Active))
            ->with(['supplier:id,name', 'type:id,name'])
            ->orderBy('expires_on')
            ->limit(self::UPCOMING_SHOWN)
            ->get(['id', 'supplier_id', 'certificate_type_id', 'expires_on'])
            ->map(fn (Certificate $certificate) => [
                'key' => "certificate-{$certificate->id}",
                'kind' => 'certificate_expires',
                'date' => $certificate->expires_on->toDateString(),
                'title' => $certificate->type->name,
                'contract_id' => null,
                'supplier_id' => $certificate->supplier_id,
                'supplier_name' => $certificate->supplier->name,
            ]);

        return array_values($contracts->concat($certificates)
            ->sortBy('date')
            ->take(self::UPCOMING_SHOWN)
            ->all());
    }

    /**
     * @return list<array{status: SupplierStatus, count: int}>
     */
    private function statusCounts(): array
    {
        $counts = $this->suppliers()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_map(fn (SupplierStatus $status) => [
            'status' => $status,
            'count' => (int) $counts->get($status->value, 0),
        ], SupplierStatus::cases());
    }

    /**
     * The biggest suppliers per currency, because amounts in different currencies cannot be compared.
     *
     * @return list<array<string, mixed>>
     */
    private function topSpend(int $year): array
    {
        $rankedSpends = SupplierSpend::query()
            ->select(['supplier_id', 'amount', 'currency_code'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY currency_code ORDER BY amount DESC, supplier_id) AS spend_rank')
            ->where('year', $year)
            ->whereHas('supplier', fn (Builder $supplier) => $supplier->whereNull('archived_at'));

        return array_values(SupplierSpend::query()
            ->fromSub($rankedSpends, 'supplier_spends')
            ->where('spend_rank', '<=', self::TOP_SPEND_SHOWN)
            ->with('supplier:id,name')
            ->orderBy('currency_code')
            ->orderBy('spend_rank')
            ->get()
            ->groupBy(fn (SupplierSpend $spend) => $spend->currency_code->value)
            ->map(fn ($spends, string $currencyCode) => [
                'currency_code' => $currencyCode,
                'suppliers' => $spends->map(fn (SupplierSpend $spend) => [
                    'id' => $spend->supplier_id,
                    'name' => $spend->supplier->name,
                    'amount' => $spend->amount,
                ])->all(),
            ])
            ->all());
    }
}
