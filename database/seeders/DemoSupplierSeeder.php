<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fills a local database with realistic suppliers for trying out the app.
 * Run with: sail artisan db:seed --class=DemoSupplierSeeder
 */
class DemoSupplierSeeder extends Seeder
{
    private const int SUPPLIER_COUNT = 2000;

    private const array CATEGORY_NAMES = [
        'IT hardware', 'Software', 'Logistics', 'Packaging', 'Raw materials', 'Electronics',
        'Office supplies', 'Facility services', 'Consulting', 'Marketing', 'Chemicals', 'Metal parts',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be added to production.');
        }

        $categories = collect(self::CATEGORY_NAMES)
            ->map(fn (string $name) => Category::firstOrCreate(['name' => $name]));

        Supplier::factory()
            ->count(self::SUPPLIER_COUNT)
            ->create()
            ->each(function (Supplier $supplier) use ($categories) {
                $supplier->categories()->attach($categories->random(fake()->numberBetween(1, 2))->pluck('id'));

                Contact::factory()->for($supplier)->primary()->create();
                Contact::factory()->count(fake()->numberBetween(0, 2))->for($supplier)->create();
            });
    }
}
