<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Supplier;
use App\Models\SupplierSpend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierSpend>
 */
class SupplierSpendFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'year' => (int) today()->year,
            'amount' => fake()->numberBetween(1_000, 900_000),
            'currency_code' => Currency::Eur,
        ];
    }
}
