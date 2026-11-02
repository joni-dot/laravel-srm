<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Ratings\UpdateRatingWeights;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RatingWeightsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UpdateRatingWeightsController extends Controller
{
    public function __invoke(RatingWeightsRequest $request, UpdateRatingWeights $updateRatingWeights): RedirectResponse
    {
        $updateRatingWeights->handle($request->weightsByCriterionId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Weights saved. Overall scores and risk levels are up to date.')]);

        return back();
    }
}
