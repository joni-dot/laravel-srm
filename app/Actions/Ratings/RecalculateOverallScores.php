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
    public function forRating(SupplierRating $rating): void
    {
        $this->recalculate($rating->id);
    }

    public function forAllRatings(): void
    {
        $this->recalculate();

        DB::update('
            UPDATE suppliers
            SET latest_rating_score = latest.overall_score
            FROM (
                SELECT DISTINCT ON (supplier_id) supplier_id, overall_score
                FROM supplier_ratings
                ORDER BY supplier_id, rated_on DESC, id DESC
            ) AS latest
            WHERE suppliers.id = latest.supplier_id
              AND suppliers.latest_rating_score IS DISTINCT FROM latest.overall_score
        ');
    }

    private function recalculate(?int $ratingId = null): void
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
}
