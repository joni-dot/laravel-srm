<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RatingCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A retired criterion is no longer used for new ratings, but keeps its place in old ones.
 *
 * @property int $id
 * @property string $name
 * @property int $weight
 * @property CarbonImmutable|null $retired_at
 */
#[Fillable(['name'])]
class RatingCriterion extends Model
{
    public const int MAX_TOTAL_WEIGHT = 100;

    /** @use HasFactory<RatingCriterionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<RatingScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(RatingScore::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inUse(Builder $query): void
    {
        $query->whereNull('retired_at');
    }
}
