<?php

namespace App\Actions\Certificates;

use App\Actions\Reminders\SyncReminders;
use App\Models\Certificate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SaveCertificate
{
    /**
     * Files live on the private disk, which is never served directly by the web server.
     */
    public const string DISK = 'local';

    public function __construct(private readonly SyncReminders $syncReminders) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $reminderDays
     */
    public function handle(Certificate $certificate, array $attributes, array $reminderDays, ?UploadedFile $file): Certificate
    {
        $replacedFilePath = $file ? $certificate->file_path : null;
        $storedFilePath = $file?->store("certificates/{$certificate->supplier_id}", self::DISK);

        try {
            DB::transaction(function () use ($certificate, $attributes, $reminderDays, $file, $storedFilePath) {
                $certificate->fill($attributes);

                if ($file && $storedFilePath) {
                    $certificate->file_path = $storedFilePath;
                    $certificate->file_name = mb_substr($file->getClientOriginalName(), 0, 255);
                    $certificate->file_mime_type = $file->getMimeType();
                    $certificate->file_size = $file->getSize() ?: null;
                }

                $certificate->save();
                $this->syncReminders->handle($certificate->reminders(), $certificate->expires_on, $reminderDays);
            });
        } catch (Throwable $exception) {
            if ($storedFilePath) {
                Storage::disk(self::DISK)->delete($storedFilePath);
            }

            throw $exception;
        }

        if ($replacedFilePath) {
            Storage::disk(self::DISK)->delete($replacedFilePath);
        }

        return $certificate;
    }
}
