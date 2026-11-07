<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\SupplierStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSuppliersRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 100;

    public const int MAX_PER_PAGE = 200;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(SupplierStatus::class)],
            'include_archived' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'cursor' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
