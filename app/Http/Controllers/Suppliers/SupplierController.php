<?php

namespace App\Http\Controllers\Suppliers;

use App\Compliance\SupplierRisk;
use App\Enums\Currency;
use App\Enums\DeliveryTerm;
use App\Enums\RiskLevel;
use App\Enums\SupplierStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SupplierRequest;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Document;
use App\Models\RatingCriterion;
use App\Models\RatingScore;
use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\SupplierSpend;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Intl\Countries;

class SupplierController extends Controller
{
    private const int SUPPLIERS_PER_PAGE = 25;

    private const int RATINGS_SHOWN = 20;

    public function index(Request $request, SupplierRisk $supplierRisk): Response
    {
        $canViewContracts = $request->user()->can('viewDetails', Contract::class);

        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'status' => $request->enum('status', SupplierStatus::class),
            'risk' => $request->enum('risk', RiskLevel::class),
            'category' => $request->integer('category') ?: null,
            'archived' => $request->boolean('archived'),
            'missing_certificates' => $request->boolean('missing_certificates'),
            'contracts_action_soon' => $canViewContracts && $request->boolean('contracts_action_soon'),
        ];

        $suppliers = Supplier::query()
            ->select(['id', 'name', 'supplier_number', 'status', 'country_code', 'city', 'latest_rating_score', 'risk_override'])
            ->with('categories:id,name')
            ->when(
                $filters['archived'],
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at'),
            )
            ->when($filters['search'] !== '', fn ($query) => $query->search($filters['search']))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['risk'], fn ($query, $level) => $query->withRiskLevel($level))
            ->when($filters['category'], fn ($query, $categoryId) => $query->whereRelation('categories', 'categories.id', $categoryId))
            ->when($filters['missing_certificates'], fn ($query) => $query->missingRequiredCertificates())
            ->when($filters['contracts_action_soon'], fn ($query) => $query->whereHas('contracts', fn ($contracts) => $contracts->needingActionSoon()))
            ->orderBy('name')
            ->paginate(self::SUPPLIERS_PER_PAGE)
            ->withQueryString();

        $risks = $supplierRisk->assess($suppliers->getCollection());

        $suppliers->through(fn (Supplier $supplier) => [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'supplier_number' => $supplier->supplier_number,
            'status' => $supplier->status,
            'country' => Countries::getName($supplier->country_code),
            'city' => $supplier->city,
            'categories' => $supplier->categories->pluck('name'),
            'missing_certificates' => $risks[$supplier->id]['missing_certificates'],
            'latest_rating_score' => $supplier->latest_rating_score,
            'risk_level' => $risks[$supplier->id]['level'],
        ]);

        return Inertia::render('suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'statuses' => SupplierStatus::options(),
            'riskLevels' => RiskLevel::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'can' => [
                'create' => $request->user()->can('create', Supplier::class),
                'viewContracts' => $canViewContracts,
            ],
        ]);
    }

    public function show(Request $request, Supplier $supplier, SupplierRisk $supplierRisk): Response
    {
        $canViewContracts = $request->user()->can('viewDetails', Contract::class);
        $canViewSpend = $request->user()->can('viewAny', SupplierSpend::class);

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

        $risk = $supplierRisk->assess(collect([$supplier]))[$supplier->id];

        return Inertia::render('suppliers/Show', [
            'supplier' => [
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
                'contacts' => $supplier->contacts->map(fn (Contact $contact) => $contact->only([
                    'id', 'name', 'job_title', 'email', 'phone', 'is_primary',
                ])),
                'certificates' => $supplier->certificates->map(fn (Certificate $certificate) => [
                    ...$certificate->only(['id', 'certificate_type_id', 'reference', 'file_name', 'file_size']),
                    'issued_on' => $certificate->issued_on?->toDateString(),
                    'expires_on' => $certificate->expires_on?->toDateString(),
                    'type' => $certificate->type->name,
                    'status' => $certificate->status(),
                    'reminder_days' => $certificate->reminders->pluck('days_before')->sortDesc()->values(),
                ]),
                'missing_certificates' => $risk['missing_certificates'],
                'documents' => $supplier->documents->map(fn (Document $document) => $document->only(['id', 'file_name', 'file_size'])),
                'contracts_count' => $supplier->contracts_count,
                'contracts' => $canViewContracts ? $this->contractSummaries($supplier) : [],
                'latest_rating_score' => $supplier->latest_rating_score,
                'risk' => [
                    'level' => $risk['level'],
                    'calculated_level' => $risk['calculated_level'],
                    'signals' => $risk['signals'],
                    'reason' => $supplier->risk_override_reason,
                    'overridden_by' => $supplier->riskOverriddenBy?->name,
                    'overridden_at' => $supplier->risk_overridden_at?->toDateString(),
                    'is_overridden' => $supplier->risk_override !== null,
                ],
                'ratings' => $supplier->ratings->map(fn (SupplierRating $rating) => [
                    ...$rating->only(['id', 'overall_score', 'comment']),
                    'rated_on' => $rating->rated_on->toDateString(),
                    'rated_by' => $rating->ratedBy?->name,
                    'scores' => $rating->scores->map(fn (RatingScore $score) => [
                        'criterion' => $score->criterion->name,
                        'score' => $score->score,
                    ]),
                ]),
                'spends' => $canViewSpend
                    ? $supplier->spends()->orderByDesc('year')->get()->map(fn (SupplierSpend $spend) => $spend->only(['id', 'year', 'amount', 'currency_code']))
                    : [],
            ],
            'ratingCriteria' => RatingCriterion::query()->inUse()->orderBy('name')->get(['id', 'name']),
            'riskLevels' => RiskLevel::options(),
            'currencies' => Currency::options(),
            'certificateTypes' => CertificateType::query()->orderBy('name')->get(['id', 'name']),
            'can' => [
                'update' => $request->user()->can('update', $supplier),
                'archive' => $request->user()->can('archive', $supplier),
                'restore' => $request->user()->can('restore', $supplier),
                'viewContracts' => $canViewContracts,
                'viewSpend' => $canViewSpend,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('suppliers/Create', $this->formOptions());
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = DB::transaction(function () use ($request) {
            $supplier = Supplier::create($request->supplierAttributes());
            $supplier->categories()->sync($request->categoryIds());

            return $supplier;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier created.')]);

        return to_route('suppliers.show', $supplier);
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('suppliers/Edit', [
            ...$this->formOptions(),
            'supplier' => [
                ...$supplier->only([
                    'id', 'name', 'supplier_number', 'status', 'responsible_user_id', 'country_code', 'business_id', 'vat_number',
                    'street_address', 'postal_code', 'city', 'website', 'email', 'phone',
                    'payment_terms', 'currency_code', 'delivery_term', 'delivery_place', 'notes',
                ]),
                'category_ids' => $supplier->categories()->pluck('categories.id'),
            ],
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        DB::transaction(function () use ($request, $supplier) {
            $supplier->update($request->supplierAttributes());
            $supplier->categories()->sync($request->categoryIds());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier updated.')]);

        return to_route('suppliers.show', $supplier);
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
     * The choices shown in the supplier form's drop-downs.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => SupplierStatus::options(),
            'responsibleUsers' => User::query()
                ->activeBuyersAndAdmins()
                ->orderBy('name')
                ->get(['id', 'name']),
            'countries' => collect(Countries::getNames())
                ->map(fn (string $name, string $code) => ['value' => $code, 'label' => $name])
                ->values(),
            'currencies' => Currency::options(),
            'deliveryTerms' => DeliveryTerm::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
