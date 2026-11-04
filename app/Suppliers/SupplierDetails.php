<?php

namespace App\Suppliers;

use App\Compliance\SupplierRisk;
use App\Models\Certificate;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Document;
use App\Models\RatingScore;
use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\SupplierSpend;
use App\Models\User;
use Symfony\Component\Intl\Countries;

/**
 * Everything the supplier page shows about one supplier, loaded with a fixed number of queries.
 * Contracts and spend are left out for people not allowed to see them.
 */
class SupplierDetails
{
    private const int RATINGS_SHOWN = 20;

    public function __construct(private SupplierRisk $supplierRisk) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Supplier $supplier, User $viewer): array
    {
        $this->loadRelations($supplier);
        $risk = $this->supplierRisk->assess(collect([$supplier]))[$supplier->id];

        return [
            ...$this->basics($supplier),
            'contacts' => $supplier->contacts->map(fn (Contact $contact) => $contact->only([
                'id', 'name', 'job_title', 'email', 'phone', 'is_primary',
            ])),
            'certificates' => $supplier->certificates->map(fn (Certificate $certificate) => $this->certificate($certificate)),
            'missing_certificates' => $risk['missing_certificates'],
            'documents' => $supplier->documents->map(fn (Document $document) => $document->only(['id', 'file_name', 'file_size'])),
            'contracts_count' => $supplier->contracts_count,
            'contracts' => $viewer->can('viewDetails', Contract::class) ? $this->contractSummaries($supplier) : [],
            'latest_rating_score' => $supplier->latest_rating_score,
            'risk' => $this->risk($supplier, $risk),
            'ratings' => $supplier->ratings->map(fn (SupplierRating $rating) => $this->rating($rating)),
            'spends' => $viewer->can('viewAny', SupplierSpend::class) ? $this->spends($supplier) : [],
        ];
    }

    private function loadRelations(Supplier $supplier): void
    {
        $supplier->loadCount('contracts');
        $supplier->load([
            'categories:id,name',
            'responsibleUser:id,name',
            'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('name'),
            'certificates' => fn ($query) => $query->with(['type:id,name', 'reminders:id,certificate_id,days_before'])->orderByDesc('expires_on'),
            'documents' => fn ($query) => $query->whereNull('contract_id')->orderBy('file_name'),
            'riskOverriddenBy:id,name',
            'ratings' => fn ($query) => $query
                ->with(['ratedBy:id,name', 'scores.criterion:id,name'])
                ->latest('rated_on')
                ->latest('id')
                ->limit(self::RATINGS_SHOWN),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function basics(Supplier $supplier): array
    {
        return [
            ...$supplier->only([
                'id', 'name', 'supplier_number', 'status', 'business_id', 'vat_number',
                'street_address', 'postal_code', 'city', 'website', 'email', 'phone',
                'payment_terms', 'delivery_place', 'notes', 'archived_at',
            ]),
            'responsible_user' => $supplier->responsibleUser?->name,
            'country' => Countries::getName($supplier->country_code),
            'currency' => $supplier->currency_code?->label(),
            'delivery_term' => $supplier->delivery_term?->label(),
            'categories' => $supplier->categories->pluck('name'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function certificate(Certificate $certificate): array
    {
        return [
            ...$certificate->only(['id', 'certificate_type_id', 'reference', 'file_name', 'file_size']),
            'issued_on' => $certificate->issued_on?->toDateString(),
            'expires_on' => $certificate->expires_on?->toDateString(),
            'type' => $certificate->type->name,
            'status' => $certificate->status(),
            'reminder_days' => $certificate->reminders->pluck('days_before')->sortDesc()->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $risk  The supplier's entry from SupplierRisk::assess().
     * @return array<string, mixed>
     */
    private function risk(Supplier $supplier, array $risk): array
    {
        return [
            'level' => $risk['level'],
            'calculated_level' => $risk['calculated_level'],
            'signals' => $risk['signals'],
            'reason' => $supplier->risk_override_reason,
            'overridden_by' => $supplier->riskOverriddenBy?->name,
            'overridden_at' => $supplier->risk_overridden_at?->toDateString(),
            'is_overridden' => $supplier->risk_override !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rating(SupplierRating $rating): array
    {
        return [
            ...$rating->only(['id', 'overall_score', 'comment']),
            'rated_on' => $rating->rated_on->toDateString(),
            'rated_by' => $rating->ratedBy?->name,
            'scores' => $rating->scores->map(fn (RatingScore $score) => [
                'criterion' => $score->criterion->name,
                'score' => $score->score,
            ]),
        ];
    }

    /**
     * Only ever call this for people allowed to see contract details.
     *
     * @return list<array<string, mixed>>
     */
    private function contractSummaries(Supplier $supplier): array
    {
        return array_values($supplier->contracts()
            ->with('type:id,name')
            ->orderByRaw('action_by ASC NULLS LAST')
            ->get()
            ->map(fn (Contract $contract) => [
                ...$contract->only(['id', 'title', 'contract_number', 'auto_renews', 'value', 'currency_code']),
                'type' => $contract->type->name,
                'health' => $contract->health(),
                'action_by' => $contract->action_by?->toDateString(),
                'value_period' => $contract->value_period?->label(),
            ])
            ->all());
    }

    /**
     * Only ever call this for people allowed to see spend.
     *
     * @return list<array<string, mixed>>
     */
    private function spends(Supplier $supplier): array
    {
        return array_values($supplier->spends()
            ->orderByDesc('year')
            ->get()
            ->map(fn (SupplierSpend $spend) => $spend->only(['id', 'year', 'amount', 'currency_code']))
            ->all());
    }
}
