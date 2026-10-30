<?php

namespace Database\Factories;

use App\Models\RatingCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatingCriterion>
 */
class RatingCriterionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, asText: true),
        ];
    }
}
