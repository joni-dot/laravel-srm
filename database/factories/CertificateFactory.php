<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'certificate_type_id' => CertificateType::factory(),
            'reference' => fake()->bothify('CERT-####-??'),
            'issued_on' => today()->subYear(),
            'expires_on' => today()->addYear(),
        ];
    }

    public function expiresInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => ['expires_on' => today()->addDays($days)]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_on' => today()->subDay()]);
    }
}
