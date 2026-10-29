<?php

namespace App\Http\Requests\Admin;

use App\Models\CertificateType;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CertificateTypeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('certificateType');

        return [
            'name' => [
                'required', 'string', 'max:100',
                new UniqueIgnoringCase(CertificateType::class, 'name', ignoreId: $type instanceof CertificateType ? $type->id : null),
            ],
            'is_required' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_required' => $this->boolean('is_required')]);
    }
}
