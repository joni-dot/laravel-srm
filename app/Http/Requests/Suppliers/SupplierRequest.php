<?php

namespace App\Http\Requests\Suppliers;

use App\Enums\Currency;
use App\Enums\DeliveryTerm;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Rules\FinnishBusinessId;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Intl\Countries;

/**
 * Used both for creating and for updating a supplier.
 */
class SupplierRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $supplierId = $supplier instanceof Supplier ? $supplier->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'supplier_number' => ['nullable', 'string', 'max:50', Rule::unique(Supplier::class)->ignore($supplierId)],
            'status' => ['required', Rule::enum(SupplierStatus::class)],
            'country_code' => ['required', 'string', Rule::in(Countries::getCountryCodes())],
            'business_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique(Supplier::class)->where('country_code', $this->input('country_code'))->ignore($supplierId),
                Rule::when($this->input('country_code') === 'FI', [new FinnishBusinessId]),
            ],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-\s]+$/'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['nullable', Rule::enum(Currency::class)],
            'delivery_term' => ['nullable', Rule::enum(DeliveryTerm::class)],
            'delivery_place' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_id.unique' => 'A supplier from this country already has this business ID.',
            'supplier_number.unique' => 'Another supplier already has this supplier number.',
            'phone.regex' => 'The phone number may only contain digits, spaces and + ( ) -.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'business_id' => 'business ID',
            'vat_number' => 'VAT number',
            'country_code' => 'country',
            'currency_code' => 'currency',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => Str::upper((string) $this->input('country_code')),
            'business_id' => $this->filled('business_id') ? Str::upper((string) $this->input('business_id')) : null,
            'vat_number' => $this->filled('vat_number') ? Str::upper(Str::remove(' ', (string) $this->input('vat_number'))) : null,
            'email' => $this->filled('email') ? Str::lower((string) $this->input('email')) : null,
            'website' => $this->websiteWithScheme(),
        ]);
    }

    /**
     * People often type "example.com", so assume https when no scheme is given.
     */
    private function websiteWithScheme(): ?string
    {
        $website = (string) $this->input('website');

        if ($website === '') {
            return null;
        }

        return Str::contains($website, '://') ? $website : "https://{$website}";
    }

    /**
     * @return array<string, mixed>
     */
    public function supplierAttributes(): array
    {
        return $this->safe()->except('category_ids');
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_values(array_map(intval(...), $this->validated('category_ids', [])));
    }
}
