<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => fake()->name(),
            'job_title' => fake()->jobTitle(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+358 40 #######'),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => true]);
    }
}
