<?php

namespace App\Console\Commands;

use App\Actions\Contracts\SaveContract;
use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Console\Command;

class RenewContracts extends Command
{
    protected $signature = 'app:renew-contracts';

    protected $description = 'Move the end date of auto-renewing contracts that were not cancelled';

    public function handle(SaveContract $saveContract): int
    {
        $renewedCount = 0;

        Contract::query()
            ->where('status', ContractStatus::Active)
            ->where('auto_renews', true)
            ->where('ends_on', '<', today())
            ->with('reminders:id,contract_id,days_before')
            ->lazyById()
            ->each(function (Contract $contract) use ($saveContract, &$renewedCount) {
                $saveContract->handle(
                    $contract,
                    ['ends_on' => $this->nextEndDate($contract)],
                    array_values($contract->reminders->map(fn ($reminder) => $reminder->days_before)->all()),
                );

                $renewedCount++;
            });

        $this->components->info("Renewed {$renewedCount} contract(s).");

        return self::SUCCESS;
    }

    /**
     * A contract may have ended more than one term ago, for example after downtime.
     */
    private function nextEndDate(Contract $contract): string
    {
        $endsOn = $contract->ends_on;

        while ($endsOn->isBefore(today())) {
            $endsOn = $endsOn->addMonthsNoOverflow((int) $contract->renewal_term_months);
        }

        return $endsOn->toDateString();
    }
}
