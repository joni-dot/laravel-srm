<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApiScope;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiClientRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'distinct', Rule::enum(ApiScope::class)],
            'responsible_user_id' => [
                'required', 'integer',
                Rule::exists(User::class, 'id')
                    ->whereIn('role', [Role::Admin->value, Role::Buyer->value])
                    ->whereNull('deactivated_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scopes' => 'access',
            'responsible_user_id' => 'responsible person',
        ];
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values($this->validated('scopes'));
    }
}
