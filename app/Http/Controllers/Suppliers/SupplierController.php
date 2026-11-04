<?php

namespace App\Http\Controllers\Suppliers;

use App\Comments\CommentFeed;
use App\Compliance\SupplierRisk;
use App\Enums\Currency;
use App\Enums\DeliveryTerm;
use App\Enums\RiskLevel;
use App\Enums\SupplierStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SupplierRequest;
use App\Models\Category;
use App\Models\CertificateType;
use App\Models\Comment;
use App\Models\Contract;
use App\Models\RatingCriterion;
use App\Models\Supplier;
use App\Models\SupplierSpend;
use App\Models\User;
use App\Suppliers\SupplierDetails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Intl\Countries;

class SupplierController extends Controller
{
    private const int SUPPLIERS_PER_PAGE = 25;

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
            'certificates_expiring' => $request->boolean('certificates_expiring'),
            'rating_overdue' => $request->boolean('rating_overdue'),
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
            ->when($filters['certificates_expiring'], fn ($query) => $query->withExpiringCertificateNotReplaced())
            ->when($filters['rating_overdue'], fn ($query) => $query->ratingOverdue())
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

    public function show(Request $request, Supplier $supplier, SupplierDetails $supplierDetails, CommentFeed $commentFeed): Response
    {
        $canViewContracts = $request->user()->can('viewDetails', Contract::class);
        $canViewSpend = $request->user()->can('viewAny', SupplierSpend::class);

        // Closures, so that "show older comments" reloads only the comments.
        return Inertia::render('suppliers/Show', [
            'supplier' => fn () => $supplierDetails->for($supplier, $request->user()),
            'comments' => fn () => $commentFeed->forSupplier($supplier, $request->user(), CommentFeed::requestedLimit($request)),
            'ratingCriteria' => fn () => RatingCriterion::query()->inUse()->orderBy('name')->get(['id', 'name']),
            'riskLevels' => RiskLevel::options(),
            'currencies' => Currency::options(),
            'certificateTypes' => fn () => CertificateType::query()->orderBy('name')->get(['id', 'name']),
            'can' => [
                'update' => $request->user()->can('update', $supplier),
                'archive' => $request->user()->can('archive', $supplier),
                'restore' => $request->user()->can('restore', $supplier),
                'comment' => $request->user()->can('create', [Comment::class, $supplier]),
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
