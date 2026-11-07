<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client;

/**
 * A system that reads SRM data through the API with the OAuth client credentials grant.
 *
 * @property string $id
 * @property string $name
 * @property list<string> $scopes
 * @property int|null $responsible_user_id
 * @property bool $revoked
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable $created_at
 */
class ApiClient extends Client
{
    public const string GRANT_TYPE = 'client_credentials';

    /**
     * Saving the time on every call would add a write to each request, so it is refreshed at most this often.
     */
    private const int LAST_USED_PRECISION_MINUTES = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('revoked', false);
    }

    public function revokeTokens(): void
    {
        $this->tokens()->where('revoked', false)->update(['revoked' => true]);
    }

    public function recordUse(): void
    {
        if ($this->last_used_at?->gt(now()->subMinutes(self::LAST_USED_PRECISION_MINUTES))) {
            return;
        }

        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }
}
