<?php

namespace App\Http\Controllers\Suppliers;

use App\Actions\Ratings\SaveRating;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\RatingRequest;
use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RatingController extends Controller
{
    public function store(RatingRequest $request, Supplier $supplier, SaveRating $saveRating): RedirectResponse
    {
        $saveRating->handle(
            $supplier,
            $request->user(),
            $request->string('rated_on')->toString(),
            $request->validated('comment'),
            $request->scoresByCriterionId(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rating added.')]);

        return back();
    }

    public function destroy(Supplier $supplier, SupplierRating $rating): RedirectResponse
    {
        DB::transaction(function () use ($supplier, $rating) {
            $rating->delete();
            $supplier->refreshLatestRatingScore();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rating deleted.')]);

        return back();
    }
}
