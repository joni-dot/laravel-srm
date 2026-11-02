<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RatingCriterion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RestoreRatingCriterionController extends Controller
{
    /**
     * Its old weight is lowered if needed, so bringing it back cannot push the total over the limit.
     */
    public function __invoke(RatingCriterion $ratingCriterion): RedirectResponse
    {
        DB::transaction(function () use ($ratingCriterion) {
            $totalInUse = (int) RatingCriterion::query()->inUse()->orderBy('id')->lockForUpdate()->pluck('weight')->sum();

            $ratingCriterion->retired_at = null;
            $ratingCriterion->weight = max(0, min($ratingCriterion->weight, RatingCriterion::MAX_TOTAL_WEIGHT - $totalInUse));
            $ratingCriterion->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion is back in use with a weight of :weight.', ['weight' => $ratingCriterion->weight])]);

        return back();
    }
}
