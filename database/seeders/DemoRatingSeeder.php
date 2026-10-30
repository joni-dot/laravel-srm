<?php

namespace Database\Seeders;

use App\Actions\Ratings\SaveRating;
use App\Enums\Currency;
use App\Models\RatingCriterion;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Gives some demo suppliers ratings and yearly spend.
 * Run after DemoSupplierSeeder with: sail artisan db:seed --class=DemoRatingSeeder
 */
class DemoRatingSeeder extends Seeder
{
    public function run(SaveRating $saveRating): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be added to production.');
        }

        $criterionIds = RatingCriterion::query()->inUse()->pluck('id');
        $rater = User::query()->activeBuyersAndAdmins()->firstOrFail();

        Supplier::query()->whereDoesntHave('ratings')->inRandomOrder()->limit(800)->get()->each(function (Supplier $supplier) use ($criterionIds, $rater, $saveRating) {
            $scores = $criterionIds->mapWithKeys(fn (int $id) => [$id => fake()->numberBetween(1, 5)])->all();

            $saveRating->handle($supplier, $rater, today()->subDays(fake()->numberBetween(0, 300))->toDateString(), null, $scores);

            $supplier->spends()->create([
                'year' => today()->year,
                'amount' => fake()->numberBetween(1_000, 900_000),
                'currency_code' => Currency::Eur,
            ]);
        });
    }
}
