<?php

namespace Database\Seeders;

use App\Actions\Certificates\SaveCertificate;
use App\Models\CertificateType;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Gives the demo suppliers certificates in every state: valid, expiring soon, expired and missing.
 * Run after DemoSupplierSeeder with: sail artisan db:seed --class=DemoCertificateSeeder
 */
class DemoCertificateSeeder extends Seeder
{
    private const array TYPES = [
        'ISO 9001' => false,
        'ISO 14001' => false,
        'Code of conduct' => true,
        'Liability insurance' => false,
    ];

    public function run(SaveCertificate $saveCertificate): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be added to production.');
        }

        $types = collect(self::TYPES)->map(fn (bool $isRequired, string $name) => CertificateType::firstOrCreate(
            ['name' => $name],
            ['is_required' => $isRequired],
        ));

        Supplier::query()->whereDoesntHave('certificates')->lazyById()->each(function (Supplier $supplier) use ($types, $saveCertificate) {
            foreach ($types->random(fake()->numberBetween(0, 3)) as $type) {
                $saveCertificate->handle(
                    $supplier->certificates()->make(),
                    [
                        'certificate_type_id' => $type->id,
                        'reference' => fake()->bothify('CERT-####-??'),
                        'issued_on' => today()->subYears(2),
                        'expires_on' => today()->addDays(fake()->numberBetween(-60, 700)),
                    ],
                    [60, 14],
                    null,
                );
            }
        });
    }
}
