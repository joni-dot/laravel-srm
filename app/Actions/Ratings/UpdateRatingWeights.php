<?php

namespace App\Actions\Ratings;

use App\Models\RatingCriterion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRatingWeights
{
    public function __construct(private readonly RecalculateOverallScores $recalculateOverallScores) {}

    /**
     * Holds the criteria locked until the scores are recalculated, so a rating saved
     * at the same moment cannot end up with a score based on the old weights.
     *
     * Only criteria in use count towards the limit; retired ones matter to old ratings alone.
     *
     * @param  array<int, int>  $weightsByCriterionId
     *
     * @throws ValidationException
     */
    public function handle(array $weightsByCriterionId): void
    {
        DB::transaction(function () use ($weightsByCriterionId) {
            $criteria = RatingCriterion::query()->orderBy('id')->lockForUpdate()->get();

            foreach ($criteria as $criterion) {
                $criterion->weight = $weightsByCriterionId[$criterion->id] ?? $criterion->weight;
            }

            $totalInUse = $criteria->whereNull('retired_at')->sum('weight');

            if ($totalInUse > RatingCriterion::MAX_TOTAL_WEIGHT) {
                throw ValidationException::withMessages([
                    'weights' => __('The weights add up to :total%. They cannot total more than :max%.', ['total' => $totalInUse, 'max' => RatingCriterion::MAX_TOTAL_WEIGHT]),
                ]);
            }

            $changed = $criteria->filter(fn (RatingCriterion $criterion) => $criterion->isDirty('weight'));

            if ($changed->isEmpty()) {
                return;
            }

            $changed->each(fn (RatingCriterion $criterion) => $criterion->save());

            $this->recalculateOverallScores->handle();
        });
    }
}
