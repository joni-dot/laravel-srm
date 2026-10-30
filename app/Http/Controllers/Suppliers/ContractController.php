<?php

namespace App\Http\Controllers\Suppliers;

use App\Actions\Contracts\DeleteContract;
use App\Actions\Contracts\SaveContract;
use App\Enums\ContractStatus;
use App\Enums\ContractValuePeriod;
use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\ContractRequest;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function show(Request $request, Supplier $supplier, Contract $contract): Response
    {
        $contract->load([
            'type:id,name',
            'owner:id,name',
            'reminders:id,contract_id,days_before',
            'documents' => fn ($query) => $query->orderBy('file_name'),
        ]);

        return Inertia::render('suppliers/contracts/Show', [
            'supplier' => $supplier->only(['id', 'name']),
            'contract' => [
                ...$contract->only([
                    'id', 'title', 'contract_number', 'auto_renews', 'renewal_term_months',
                    'notice_period_days', 'value', 'currency_code', 'notes',
                ]),
                'type' => $contract->type->name,
                'owner' => $contract->owner?->name,
                'health' => $contract->health(),
                'starts_on' => $contract->starts_on?->toDateString(),
                'ends_on' => $contract->ends_on?->toDateString(),
                'action_by' => $contract->action_by?->toDateString(),
                'value_period' => $contract->value_period?->label(),
                'reminder_days' => $contract->reminders->pluck('days_before')->sortDesc()->values(),
                'documents' => $contract->documents->map(fn (Document $document) => $document->only(['id', 'file_name', 'file_size'])),
            ],
            'can' => [
                'update' => $request->user()->can('update', $supplier),
            ],
        ]);
    }

    public function create(Supplier $supplier): Response
    {
        return Inertia::render('suppliers/contracts/Create', [
            ...$this->formOptions(),
            'supplier' => $supplier->only(['id', 'name']),
        ]);
    }

    public function store(ContractRequest $request, Supplier $supplier, SaveContract $saveContract): RedirectResponse
    {
        $contract = $saveContract->handle($supplier->contracts()->make(), $request->contractAttributes(), $request->reminderDays());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contract added.')]);

        return to_route('suppliers.contracts.show', [$supplier, $contract]);
    }

    public function edit(Supplier $supplier, Contract $contract): Response
    {
        return Inertia::render('suppliers/contracts/Edit', [
            ...$this->formOptions(),
            'supplier' => $supplier->only(['id', 'name']),
            'contract' => [
                ...$contract->only([
                    'id', 'title', 'contract_number', 'contract_type_id', 'status', 'owner_user_id', 'auto_renews',
                    'renewal_term_months', 'notice_period_days', 'value', 'currency_code', 'value_period', 'notes',
                ]),
                'starts_on' => $contract->starts_on?->toDateString(),
                'ends_on' => $contract->ends_on?->toDateString(),
                'reminder_days' => $contract->reminders()->orderByDesc('days_before')->pluck('days_before'),
            ],
        ]);
    }

    public function update(ContractRequest $request, Supplier $supplier, Contract $contract, SaveContract $saveContract): RedirectResponse
    {
        $saveContract->handle($contract, $request->contractAttributes(), $request->reminderDays());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contract updated.')]);

        return to_route('suppliers.contracts.show', [$supplier, $contract]);
    }

    public function destroy(Supplier $supplier, Contract $contract, DeleteContract $deleteContract): RedirectResponse
    {
        $deleteContract->handle($contract);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contract deleted.')]);

        return to_route('suppliers.show', $supplier);
    }

    /**
     * The choices shown in the contract form's drop-downs.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => ContractStatus::options(),
            'contractTypes' => ContractType::query()->orderBy('name')->get(['id', 'name']),
            'owners' => User::query()->activeBuyersAndAdmins()->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::options(),
            'valuePeriods' => ContractValuePeriod::options(),
        ];
    }
}
