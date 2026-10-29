<?php

namespace App\Http\Requests\Suppliers;

use App\Models\CertificateType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class CertificateRequest extends FormRequest
{
    public const int MAX_FILE_SIZE_KB = 10 * 1024;

    public const int MAX_REMINDERS = 5;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'certificate_type_id' => ['required', 'integer', Rule::exists(CertificateType::class, 'id')],
            'reference' => ['nullable', 'string', 'max:100'],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issued_on'],
            'reminder_days' => ['array', 'max:'.self::MAX_REMINDERS],
            'reminder_days.*' => ['integer', 'between:1,365', 'distinct'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_SIZE_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certificate_type_id' => 'certificate type',
            'issued_on' => 'issue date',
            'expires_on' => 'expiry date',
            'reminder_days.*' => 'reminder',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function certificateAttributes(): array
    {
        return $this->safe()->only(['certificate_type_id', 'reference', 'issued_on', 'expires_on']);
    }

    /**
     * @return list<int>
     */
    public function reminderDays(): array
    {
        return array_values(array_map(intval(...), $this->validated('reminder_days', [])));
    }

    public function uploadedFile(): ?UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : null;
    }
}
