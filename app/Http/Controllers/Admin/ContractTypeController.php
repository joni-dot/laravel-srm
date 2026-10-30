<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContractTypeRequest;
use App\Models\ContractType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContractTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/contract-types/Index', [
            'contractTypes' => ContractType::query()
                ->select(['id', 'name'])
                ->withCount('contracts')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(ContractTypeRequest $request): RedirectResponse
    {
        ContractType::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contract type added.')]);

        return back();
    }

    public function update(ContractTypeRequest $request, ContractType $contractType): RedirectResponse
    {
        $contractType->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contract type renamed.')]);

        return back();
    }

    /**
     * Types that contracts still use cannot be deleted, so no contract loses its type.
     */
    public function destroy(ContractType $contractType): RedirectResponse
    {
        if ($contractType->contracts()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This type is still used by contracts, so it cannot be deleted.')]);

            return back();
        }

        $contractType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contract type deleted.')]);

        return back();
    }
}
