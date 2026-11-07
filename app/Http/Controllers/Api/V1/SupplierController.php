<?php

namespace App\Http\Controllers\Api\V1;

use App\Compliance\SupplierRisk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListSuppliersRequest;
use App\Http\Resources\Api\V1\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    /**
     * Ordered by id with a cursor, so a system reading page after page never skips or repeats a supplier.
     */
    public function index(ListSuppliersRequest $request, SupplierRisk $supplierRisk): JsonResponse
    {
        $suppliers = Supplier::query()
            ->select(SupplierResource::COLUMNS)
            ->with('categories:id,name')
            ->unless($request->boolean('include_archived'), fn ($query) => $query->whereNull('archived_at'))
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->date('updated_since'), fn ($query, $since) => $query->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->cursorPaginate($request->perPage());

        $risks = $supplierRisk->assess($suppliers->getCollection());

        return response()->json([
            'data' => $suppliers->getCollection()->map(
                fn (Supplier $supplier) => new SupplierResource($supplier, $risks[$supplier->id]['level']),
            ),
            'next_cursor' => $suppliers->nextCursor()?->encode(),
        ]);
    }

    public function show(Supplier $supplier, SupplierRisk $supplierRisk): SupplierResource
    {
        $supplier->load('categories:id,name');

        return new SupplierResource($supplier, $supplierRisk->assess(collect([$supplier]))[$supplier->id]['level']);
    }
}
