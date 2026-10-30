<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Ratings\UpdateRatingWeights;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RatingCriterionRequest;
use App\Http\Requests\Admin\RatingWeightsRequest;
use App\Models\RatingCriterion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RatingCriterionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/rating-criteria/Index', [
            'criteria' => RatingCriterion::query()
                ->select(['id', 'name', 'weight', 'retired_at'])
                ->withCount('scores')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(RatingCriterionRequest $request): RedirectResponse
    {
        RatingCriterion::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion added. It does not count until you give it a weight.')]);

        return back();
    }

    public function update(RatingCriterionRequest $request, RatingCriterion $ratingCriterion): RedirectResponse
    {
        $ratingCriterion->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion renamed.')]);

        return back();
    }

    public function updateWeights(RatingWeightsRequest $request, UpdateRatingWeights $updateRatingWeights): RedirectResponse
    {
        $updateRatingWeights->handle($request->weightsByCriterionId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Weights saved. Overall scores and risk levels are up to date.')]);

        return back();
    }

    /**
     * A criterion that old ratings use is retired instead of deleted, so their history stays complete.
     */
    public function destroy(RatingCriterion $ratingCriterion): RedirectResponse
    {
        if ($ratingCriterion->scores()->exists()) {
            $ratingCriterion->retired_at = now();
            $ratingCriterion->save();

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion retired. Old ratings keep it, new ratings will not use it.')]);

            return back();
        }

        $ratingCriterion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion deleted.')]);

        return back();
    }

    /**
     * Its old weight is lowered if needed, so bringing it back cannot push the total over the limit.
     */
    public function restore(RatingCriterion $ratingCriterion): RedirectResponse
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
