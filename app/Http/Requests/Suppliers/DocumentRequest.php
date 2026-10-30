<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class DocumentRequest extends FormRequest
{
    public const int MAX_FILE_SIZE_KB = 20 * 1024;

    /**
     * Word and Excel files are limited to the modern formats that cannot contain macros.
     * The extension is checked too, because a macro-enabled .xlsm has the same content type as an .xlsx.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,docx,xlsx', 'extensions:pdf,jpg,jpeg,png,docx,xlsx', 'max:'.self::MAX_FILE_SIZE_KB],
        ];
    }

    public function uploadedFile(): UploadedFile
    {
        $file = $this->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
