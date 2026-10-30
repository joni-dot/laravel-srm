<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SpendRequest;
use App\Models\Supplier;
use App\Models\SupplierSpend;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SpendController extends Controller
{
    /**
     * A supplier has one amount per year, so saving a year again replaces its amount.
     */
    public function store(SpendRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->spends()->updateOrCreate(
            ['year' => $request->integer('year')],
            $request->safe()->only(['amount', 'currency_code']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Spend saved.')]);

        return back();
    }

    public function destroy(Supplier $supplier, SupplierSpend $spend): RedirectResponse
    {
        $spend->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Spend deleted.')]);

        return back();
    }
}
