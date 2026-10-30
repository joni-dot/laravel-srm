<?php

namespace App\Actions\Ratings;

use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SaveRating
{
    /**
     * @param  array<int, int>  $scoresByCriterionId
     */
    public function handle(Supplier $supplier, User $ratedBy, string $ratedOn, ?string $comment, array $scoresByCriterionId): SupplierRating
    {
        return DB::transaction(function () use ($supplier, $ratedBy, $ratedOn, $comment, $scoresByCriterionId) {
            $rating = new SupplierRating;
            $rating->supplier_id = $supplier->id;
            $rating->rated_by_user_id = $ratedBy->id;
            $rating->rated_on = CarbonImmutable::parse($ratedOn);
            $rating->comment = $comment;
            $rating->overall_score = (string) round(array_sum($scoresByCriterionId) / count($scoresByCriterionId), 2);
            $rating->save();

            foreach ($scoresByCriterionId as $criterionId => $score) {
                $rating->scores()->create(['rating_criterion_id' => $criterionId, 'score' => $score]);
            }

            $supplier->refreshLatestRatingScore();

            return $rating;
        });
    }
}
