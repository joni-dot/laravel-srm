<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Enums\SettingKey;
use App\Models\Contract;
use App\Models\ContractReminder;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ContractsNeedAction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendContractReminders extends Command
{
    protected $signature = 'app:send-contract-reminders';

    protected $description = 'Email reminders about contracts that are about to end or must be cancelled soon';

    public function handle(): int
    {
        $fallbackEmail = Setting::get(SettingKey::ReminderEmail);

        $this->dueReminders()
            ->groupBy(fn (ContractReminder $reminder) => $this->recipientFor($reminder->contract, $fallbackEmail) ?? '')
            ->each(function (Collection $reminders, string $recipient) {
                if ($recipient === '') {
                    Log::warning('Contract reminders could not be sent: no contract owner, no responsible buyer and no reminder email is set.', [
                        'reminder_ids' => $reminders->pluck('id')->all(),
                    ]);

                    return;
                }

                $this->send($recipient, $reminders);
            });

        return self::SUCCESS;
    }

    /**
     * Unsent reminders whose day has come, for active contracts whose deadline has not passed.
     *
     * @return Collection<int, ContractReminder>
     */
    private function dueReminders(): Collection
    {
        return ContractReminder::query()
            ->whereNull('sent_at')
            ->where('remind_on', '<=', today())
            ->whereHas('contract', fn ($contract) => $contract
                ->where('status', ContractStatus::Active)
                ->where('action_by', '>=', today())
                ->whereRelation('supplier', 'archived_at', null))
            ->with([
                'contract.owner:id,email,role,deactivated_at',
                'contract.supplier:id,name,responsible_user_id',
                'contract.supplier.responsibleUser:id,email,role,deactivated_at',
            ])
            ->get();
    }

    /**
     * Only people allowed to see contract details may receive these emails.
     */
    private function recipientFor(Contract $contract, ?string $fallbackEmail): ?string
    {
        foreach ([$contract->owner, $contract->supplier->responsibleUser] as $user) {
            if ($user instanceof User && ! $user->isDeactivated() && $user->can('viewDetails', Contract::class)) {
                return $user->email;
            }
        }

        return $fallbackEmail;
    }

    /**
     * @param  Collection<int, ContractReminder>  $reminders
     */
    private function send(string $recipient, Collection $reminders): void
    {
        $contracts = $reminders->map(fn (ContractReminder $reminder) => $reminder->contract)->unique('id');

        DB::transaction(function () use ($recipient, $reminders, $contracts) {
            ContractReminder::query()->whereKey($reminders->pluck('id'))->update(['sent_at' => now()]);

            Notification::route('mail', $recipient)->notify(new ContractsNeedAction($contracts));
        });

        $this->components->info("Sent {$contracts->count()} contract reminder(s) to {$recipient}.");
    }
}
