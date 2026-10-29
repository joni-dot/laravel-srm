<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\DeliveryTerm;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'supplier_number' => fake()->unique()->numerify('S-#####'),
            'status' => fake()->randomElement(SupplierStatus::cases()),
            'country_code' => fake()->randomElement(['DE', 'SE', 'US', 'GB', 'NL', 'EE']),
            'business_id' => fake()->unique()->numerify('########'),
            'vat_number' => null,
            'street_address' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'website' => 'https://'.fake()->domainName(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('+49 ### #######'),
            'payment_terms' => fake()->randomElement(['14 days net', '30 days net', '60 days net']),
            'currency_code' => fake()->randomElement([Currency::Eur, Currency::Usd, Currency::Sek]),
            'delivery_term' => fake()->randomElement(DeliveryTerm::cases()),
            'delivery_place' => fake()->city(),
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SupplierStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['archived_at' => now()]);
    }
}
