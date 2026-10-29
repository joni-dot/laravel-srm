<?php

namespace App\Http\Controllers\Suppliers;

use App\Enums\Currency;
use App\Enums\DeliveryTerm;
use App\Enums\SupplierStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SupplierRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Intl\Countries;

class SupplierController extends Controller
{
    private const int SUPPLIERS_PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'status' => $request->enum('status', SupplierStatus::class),
            'category' => $request->integer('category') ?: null,
            'archived' => $request->boolean('archived'),
        ];

        $suppliers = Supplier::query()
            ->select(['id', 'name', 'supplier_number', 'status', 'country_code', 'city'])
            ->with('categories:id,name')
            ->when(
                $filters['archived'],
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at'),
            )
            ->when($filters['search'] !== '', fn ($query) => $query->search($filters['search']))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category'], fn ($query, $categoryId) => $query->whereRelation('categories', 'categories.id', $categoryId))
            ->orderBy('name')
            ->paginate(self::SUPPLIERS_PER_PAGE)
            ->withQueryString()
            ->through(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'supplier_number' => $supplier->supplier_number,
                'status' => $supplier->status,
                'country' => Countries::getName($supplier->country_code),
                'city' => $supplier->city,
                'categories' => $supplier->categories->pluck('name'),
            ]);

        return Inertia::render('suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'statuses' => SupplierStatus::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'can' => [
                'create' => $request->user()->can('create', Supplier::class),
            ],
        ]);
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $supplier->load([
            'categories:id,name',
            'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('name'),
        ]);

        return Inertia::render('suppliers/Show', [
            'supplier' => [
                ...$supplier->only([
                    'id', 'name', 'supplier_number', 'status', 'business_id', 'vat_number',
                    'street_address', 'postal_code', 'city', 'website', 'email', 'phone',
                    'payment_terms', 'delivery_place', 'notes', 'archived_at',
                ]),
                'country' => Countries::getName($supplier->country_code),
                'currency' => $supplier->currency_code?->label(),
                'delivery_term' => $supplier->delivery_term?->label(),
                'categories' => $supplier->categories->pluck('name'),
                'contacts' => $supplier->contacts->map(fn (Contact $contact) => $contact->only([
                    'id', 'name', 'job_title', 'email', 'phone', 'is_primary',
                ])),
            ],
            'can' => [
                'update' => $request->user()->can('update', $supplier),
                'archive' => $request->user()->can('archive', $supplier),
                'restore' => $request->user()->can('restore', $supplier),
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
                    'id', 'name', 'supplier_number', 'status', 'country_code', 'business_id', 'vat_number',
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
            'countries' => collect(Countries::getNames())
                ->map(fn (string $name, string $code) => ['value' => $code, 'label' => $name])
                ->values(),
            'currencies' => Currency::options(),
            'deliveryTerms' => DeliveryTerm::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
