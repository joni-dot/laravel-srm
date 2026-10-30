<?php

namespace App\Http\Requests\Admin;

use App\Models\ContractType;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContractTypeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('contractType');

        return [
            'name' => [
                'required', 'string', 'max:100',
                new UniqueIgnoringCase(ContractType::class, 'name', ignoreId: $type instanceof ContractType ? $type->id : null),
            ],
        ];
    }
}
