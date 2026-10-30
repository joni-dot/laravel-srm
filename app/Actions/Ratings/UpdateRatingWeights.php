<?php

namespace App\Actions\Ratings;

use App\Models\RatingCriterion;
use Illuminate\Support\Facades\DB;

class UpdateRatingWeights
{
    public function __construct(private readonly RecalculateOverallScores $recalculateOverallScores) {}

    /**
     * Holds the criteria locked until the scores are recalculated, so a rating saved
     * at the same moment cannot end up with a score based on the old weights.
     *
     * @param  array<int, int>  $weightsByCriterionId
     */
    public function handle(array $weightsByCriterionId): void
    {
        DB::transaction(function () use ($weightsByCriterionId) {
            $criteria = RatingCriterion::query()
                ->whereKey(array_keys($weightsByCriterionId))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($criteria as $criterion) {
                $criterion->weight = $weightsByCriterionId[$criterion->id];
            }

            $changed = $criteria->filter(fn (RatingCriterion $criterion) => $criterion->isDirty('weight'));

            if ($changed->isEmpty()) {
                return;
            }

            $changed->each(fn (RatingCriterion $criterion) => $criterion->save());

            $this->recalculateOverallScores->forAllRatings();
        });
    }
}
