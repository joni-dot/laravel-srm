<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierRating>
 */
class SupplierRatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'rated_on' => today(),
            'overall_score' => '4.00',
        ];
    }
}
