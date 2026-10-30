<?php

namespace App\Actions\Contracts;

use App\Actions\Reminders\SyncReminders;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

class SaveContract
{
    public function __construct(private readonly SyncReminders $syncReminders) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $reminderDays
     */
    public function handle(Contract $contract, array $attributes, array $reminderDays): Contract
    {
        DB::transaction(function () use ($contract, $attributes, $reminderDays) {
            $contract->fill($attributes)->save();

            // The database calculates action_by, so read it back before scheduling reminders.
            $contract->refresh();

            $this->syncReminders->handle($contract->reminders(), $contract->action_by, $reminderDays);
        });

        return $contract;
    }
}
