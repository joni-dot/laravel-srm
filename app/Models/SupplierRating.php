<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SupplierRatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int|null $rated_by_user_id
 * @property CarbonImmutable $rated_on
 * @property string $overall_score
 * @property string|null $comment
 */
class SupplierRating extends Model
{
    /** @use HasFactory<SupplierRatingFactory> */
    use HasFactory;

    public const int OVERDUE_AFTER_MONTHS = 12;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rated_on' => 'date',
            'overall_score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by_user_id');
    }

    /**
     * @return HasMany<RatingScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(RatingScore::class);
    }
}
