<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RatingCriterionRequest;
use App\Models\RatingCriterion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RatingCriterionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/rating-criteria/Index', [
            'criteria' => RatingCriterion::query()
                ->select(['id', 'name', 'retired_at'])
                ->withCount('scores')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(RatingCriterionRequest $request): RedirectResponse
    {
        RatingCriterion::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion added.')]);

        return back();
    }

    public function update(RatingCriterionRequest $request, RatingCriterion $ratingCriterion): RedirectResponse
    {
        $ratingCriterion->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion renamed.')]);

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

    public function restore(RatingCriterion $ratingCriterion): RedirectResponse
    {
        $ratingCriterion->retired_at = null;
        $ratingCriterion->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion is back in use.')]);

        return back();
    }
}
