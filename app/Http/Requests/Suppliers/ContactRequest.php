<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ContactRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-\s]+$/'],
            'is_primary' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number may only contain digits, spaces and + ( ) -.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? Str::lower((string) $this->input('email')) : null,
            'is_primary' => $this->boolean('is_primary'),
        ]);
    }
}
