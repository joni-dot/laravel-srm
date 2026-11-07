<?php

namespace App\Actions\Ratings;

use App\Models\SupplierRating;
use Illuminate\Support\Facades\DB;

/**
 * The one place that works out overall scores: the weighted average of a rating's own scores.
 * Old ratings may lack newer criteria, so only the weights of the scores they have count.
 * When none of those has any weight, every score counts the same.
 */
class RecalculateOverallScores
{
    /**
     * Recalculates the given rating, or every rating when none is given,
     * and keeps the affected suppliers' latest rating score in step.
     */
    public function handle(?SupplierRating $rating = null): void
    {
        $this->updateOverallScores($rating?->id);
        $this->updateLatestSupplierScores($rating?->supplier_id);
    }

    private function updateOverallScores(?int $ratingId): void
    {
        $calculated = DB::table('rating_scores')
            ->join('rating_criteria', 'rating_criteria.id', '=', 'rating_scores.rating_criterion_id')
            ->when($ratingId, fn ($query) => $query->where('rating_scores.supplier_rating_id', $ratingId))
            ->groupBy('rating_scores.supplier_rating_id')
            ->select('rating_scores.supplier_rating_id')
            ->selectRaw('ROUND(COALESCE(SUM(rating_scores.score * rating_criteria.weight)::numeric / NULLIF(SUM(rating_criteria.weight), 0), AVG(rating_scores.score)), 2) AS score');

        DB::update("
            UPDATE supplier_ratings
            SET overall_score = calculated.score
            FROM ({$calculated->toSql()}) AS calculated
            WHERE supplier_ratings.id = calculated.supplier_rating_id
              AND supplier_ratings.overall_score IS DISTINCT FROM calculated.score
        ", $calculated->getBindings());
    }

    private function updateLatestSupplierScores(?int $supplierId): void
    {
        $latest = DB::table('supplier_ratings')
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->distinct('supplier_id')
            ->select('supplier_id', 'overall_score')
            ->orderBy('supplier_id')
            ->orderByDesc('rated_on')
            ->orderByDesc('id');

        DB::update("
            UPDATE suppliers
            SET latest_rating_score = latest.overall_score
            FROM ({$latest->toSql()}) AS latest
            WHERE suppliers.id = latest.supplier_id
              AND suppliers.latest_rating_score IS DISTINCT FROM latest.overall_score
        ", $latest->getBindings());
    }
}
