<?php

namespace App\Actions\Reminders;

use App\Models\CertificateReminder;
use App\Models\ContractReminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncReminders
{
    /**
     * Schedule reminders a number of days before the target date.
     * A reminder that is unchanged is kept, so one that was already sent is not sent again.
     *
     * @param  HasMany<covariant CertificateReminder|ContractReminder, *>  $reminders
     * @param  list<int>  $daysBefore
     */
    public function handle(HasMany $reminders, ?CarbonImmutable $targetDate, array $daysBefore): void
    {
        $wantedDays = $targetDate === null ? [] : $daysBefore;

        (clone $reminders)->whereNotIn('days_before', $wantedDays)->delete();

        if ($targetDate === null) {
            return;
        }

        foreach ($wantedDays as $days) {
            $remindOn = $targetDate->subDays($days);
            $reminder = (clone $reminders)->firstOrNew(['days_before' => $days]);

            if ($reminder->exists && $reminder->remind_on->equalTo($remindOn)) {
                continue;
            }

            $reminder->remind_on = $remindOn;
            $reminder->sent_at = null;
            $reminder->save();
        }
    }
}
