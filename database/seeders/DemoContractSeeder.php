<?php

namespace Database\Seeders;

use App\Actions\Contracts\SaveContract;
use App\Enums\ContractStatus;
use App\Enums\ContractValuePeriod;
use App\Enums\Currency;
use App\Models\ContractType;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Gives some demo suppliers contracts: running, ending soon, ended, and renewing automatically.
 * Run after DemoSupplierSeeder with: sail artisan db:seed --class=DemoContractSeeder
 */
class DemoContractSeeder extends Seeder
{
    public function run(SaveContract $saveContract): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be added to production.');
        }

        $typeIds = ContractType::query()->pluck('id');

        Supplier::query()->whereDoesntHave('contracts')->inRandomOrder()->limit(600)->get()->each(function (Supplier $supplier) use ($typeIds, $saveContract) {
            $autoRenews = fake()->boolean(40);

            $saveContract->handle(
                $supplier->contracts()->make(),
                [
                    'title' => fake()->randomElement(['Supply', 'Maintenance', 'Logistics', 'Licence']).' agreement '.fake()->numerify('####'),
                    'contract_number' => fake()->unique()->numerify('C-######'),
                    'contract_type_id' => $typeIds->random(),
                    'status' => fake()->randomElement([ContractStatus::Active, ContractStatus::Active, ContractStatus::Active, ContractStatus::Draft, ContractStatus::Terminated]),
                    'starts_on' => today()->subYear(),
                    'ends_on' => today()->addDays(fake()->numberBetween(-30, 500)),
                    'auto_renews' => $autoRenews,
                    'renewal_term_months' => $autoRenews ? 12 : null,
                    'notice_period_days' => $autoRenews ? 90 : null,
                    'value' => fake()->numberBetween(1_000, 500_000),
                    'currency_code' => Currency::Eur,
                    'value_period' => ContractValuePeriod::Yearly,
                ],
                [60, 14],
            );
        });
    }
}
