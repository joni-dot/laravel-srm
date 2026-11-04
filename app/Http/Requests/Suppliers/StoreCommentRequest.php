<?php

namespace App\Http\Requests\Suppliers;

use App\Models\Contract;
use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A new comment is about the supplier itself, or about one of its own contracts.
 */
class StoreCommentRequest extends CommentRequest
{
    public function authorize(): bool
    {
        return ! $this->filled('contract_id') || $this->user()->can('viewDetails', Contract::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        assert($supplier instanceof Supplier);

        return [
            ...parent::rules(),
            'contract_id' => [
                'nullable', 'integer',
                Rule::exists('contracts', 'id')->where('supplier_id', $supplier->id),
            ],
        ];
    }
}
