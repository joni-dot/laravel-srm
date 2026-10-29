<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RestoreSupplierController extends Controller
{
    public function __invoke(Supplier $supplier): RedirectResponse
    {
        $supplier->archived_at = null;
        $supplier->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was restored.', ['name' => $supplier->name])]);

        return to_route('suppliers.show', $supplier);
    }
}
