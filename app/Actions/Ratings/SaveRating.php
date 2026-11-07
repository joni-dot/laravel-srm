<?php

namespace App\Actions\Ratings;

use App\Models\RatingCriterion;
use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SaveRating
{
    public function __construct(private readonly RecalculateOverallScores $recalculateOverallScores) {}

    /**
     * Holds a shared lock on the criteria, so their weights cannot change until this rating is saved.
     *
     * @param  array<int, int>  $scoresByCriterionId
     */
    public function handle(Supplier $supplier, User $ratedBy, string $ratedOn, ?string $comment, array $scoresByCriterionId): SupplierRating
    {
        return DB::transaction(function () use ($supplier, $ratedBy, $ratedOn, $comment, $scoresByCriterionId) {
            RatingCriterion::query()->whereKey(array_keys($scoresByCriterionId))->orderBy('id')->sharedLock()->pluck('id');

            $rating = new SupplierRating;
            $rating->supplier_id = $supplier->id;
            $rating->rated_by_user_id = $ratedBy->id;
            $rating->rated_on = CarbonImmutable::parse($ratedOn);
            $rating->comment = $comment;
            $rating->overall_score = '0.00';
            $rating->save();

            foreach ($scoresByCriterionId as $criterionId => $score) {
                $rating->scores()->create(['rating_criterion_id' => $criterionId, 'score' => $score]);
            }

            $this->recalculateOverallScores->handle($rating);

            return $rating->refresh();
        });
    }
}
