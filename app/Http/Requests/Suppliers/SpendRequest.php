<?php

namespace App\Http\Requests\Suppliers;

use App\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpendRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,'.now()->year],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'currency_code' => ['required', Rule::enum(Currency::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'currency_code' => 'currency',
        ];
    }
}
