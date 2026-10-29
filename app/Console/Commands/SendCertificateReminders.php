<?php

namespace App\Console\Commands;

use App\Enums\SettingKey;
use App\Models\Certificate;
use App\Models\CertificateReminder;
use App\Models\Setting;
use App\Notifications\CertificatesExpiring;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendCertificateReminders extends Command
{
    protected $signature = 'app:send-certificate-reminders';

    protected $description = 'Email reminders about certificates that are about to expire';

    public function handle(): int
    {
        $dueReminders = $this->dueReminders();

        if ($dueReminders->isEmpty()) {
            $this->components->info('No reminders are due.');

            return self::SUCCESS;
        }

        $fallbackEmail = Setting::get(SettingKey::ReminderEmail);

        $dueReminders
            ->groupBy(fn (CertificateReminder $reminder) => $this->recipientFor($reminder->certificate, $fallbackEmail) ?? '')
            ->each(function (Collection $reminders, string $recipient) {
                if ($recipient === '') {
                    Log::warning('Certificate reminders could not be sent: no responsible buyer and no reminder email is set.', [
                        'reminder_ids' => $reminders->pluck('id')->all(),
                    ]);

                    return;
                }

                $this->send($recipient, $reminders);
            });

        return self::SUCCESS;
    }

    /**
     * Unsent reminders whose day has come, for certificates that have not expired yet.
     *
     * @return Collection<int, CertificateReminder>
     */
    private function dueReminders(): Collection
    {
        return CertificateReminder::query()
            ->whereNull('sent_at')
            ->where('remind_on', '<=', today())
            ->whereHas('certificate', fn ($certificate) => $certificate
                ->where('expires_on', '>=', today())
                ->whereRelation('supplier', 'archived_at', null))
            ->with([
                'certificate.type:id,name',
                'certificate.supplier:id,name,responsible_user_id',
                'certificate.supplier.responsibleUser:id,email,deactivated_at',
            ])
            ->get();
    }

    private function recipientFor(Certificate $certificate, ?string $fallbackEmail): ?string
    {
        $responsibleUser = $certificate->supplier->responsibleUser;

        if ($responsibleUser && ! $responsibleUser->isDeactivated()) {
            return $responsibleUser->email;
        }

        return $fallbackEmail;
    }

    /**
     * @param  Collection<int, CertificateReminder>  $reminders
     */
    private function send(string $recipient, Collection $reminders): void
    {
        $certificates = $reminders->map(fn (CertificateReminder $reminder) => $reminder->certificate)->unique('id');

        DB::transaction(function () use ($recipient, $reminders, $certificates) {
            CertificateReminder::query()->whereKey($reminders->pluck('id'))->update(['sent_at' => now()]);

            Notification::route('mail', $recipient)->notify(new CertificatesExpiring($certificates));
        });

        $this->components->info("Sent {$certificates->count()} certificate reminder(s) to {$recipient}.");
    }
}
