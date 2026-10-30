<?php

namespace App\Http\Requests\Suppliers;

use App\Enums\ContractStatus;
use App\Enums\ContractValuePeriod;
use App\Enums\Currency;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractRequest extends FormRequest
{
    public const int MAX_REMINDERS = 5;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $contract = $this->route('contract');

        return [
            'title' => ['required', 'string', 'max:255'],
            'contract_number' => ['nullable', 'string', 'max:50', Rule::unique(Contract::class)->ignore($contract instanceof Contract ? $contract->id : null)],
            'contract_type_id' => ['required', 'integer', Rule::exists(ContractType::class, 'id')],
            'status' => ['required', Rule::enum(ContractStatus::class)],
            'owner_user_id' => [
                'nullable', 'integer',
                Rule::exists(User::class, 'id')->where(fn ($query) => $query->whereIn('role', ['admin', 'buyer'])->whereNull('deactivated_at')),
            ],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'required_if_accepted:auto_renews'],
            'auto_renews' => ['boolean'],
            'renewal_term_months' => ['nullable', 'integer', 'between:1,120', 'required_if_accepted:auto_renews'],
            'notice_period_days' => ['nullable', 'integer', 'between:0,730'],
            'value' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'currency_code' => ['nullable', Rule::enum(Currency::class), 'required_with:value'],
            'value_period' => ['nullable', Rule::enum(ContractValuePeriod::class), 'required_with:value'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reminder_days' => ['array', 'max:'.self::MAX_REMINDERS],
            'reminder_days.*' => ['integer', 'between:1,365', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'contract_type_id' => 'contract type',
            'owner_user_id' => 'contract owner',
            'starts_on' => 'start date',
            'ends_on' => 'end date',
            'renewal_term_months' => 'renewal term',
            'notice_period_days' => 'notice period',
            'currency_code' => 'currency',
            'value_period' => 'value period',
            'reminder_days.*' => 'reminder',
        ];
    }

    protected function prepareForValidation(): void
    {
        $autoRenews = $this->boolean('auto_renews');

        $this->merge([
            'auto_renews' => $autoRenews,
            'renewal_term_months' => $autoRenews ? $this->input('renewal_term_months') : null,
            'notice_period_days' => $autoRenews ? $this->input('notice_period_days') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function contractAttributes(): array
    {
        return $this->safe()->except('reminder_days');
    }

    /**
     * @return list<int>
     */
    public function reminderDays(): array
    {
        return array_values(array_map(intval(...), $this->validated('reminder_days', [])));
    }
}
