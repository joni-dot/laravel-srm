<?php

namespace App\Http\Requests\Suppliers;

use App\Enums\RiskLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RiskOverrideRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(RiskLevel::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
