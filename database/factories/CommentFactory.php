<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'user_id' => User::factory()->buyer(),
            'body' => fake()->sentence(),
        ];
    }

    public function aboutContract(Contract $contract): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_id' => $contract->supplier_id,
            'contract_id' => $contract->id,
        ]);
    }
}
