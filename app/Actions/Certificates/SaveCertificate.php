<?php

namespace App\Actions\Certificates;

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
                $this->syncReminders($certificate, $reminderDays);
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

    /**
     * Keep a reminder that is unchanged, so one that was already sent is not sent again.
     *
     * @param  list<int>  $reminderDays
     */
    private function syncReminders(Certificate $certificate, array $reminderDays): void
    {
        $expiresOn = $certificate->expires_on;
        $wantedDays = $expiresOn === null ? [] : $reminderDays;

        $certificate->reminders()->whereNotIn('days_before', $wantedDays)->delete();

        foreach ($wantedDays as $daysBefore) {
            $remindOn = $expiresOn->subDays($daysBefore);
            $reminder = $certificate->reminders()->firstOrNew(['days_before' => $daysBefore]);

            if ($reminder->exists && $reminder->remind_on->equalTo($remindOn)) {
                continue;
            }

            $reminder->remind_on = $remindOn;
            $reminder->sent_at = null;
            $reminder->save();
        }
    }
}
