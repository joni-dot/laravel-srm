<?php

namespace App\Actions\ApiClients;

use App\Models\ApiClient;
use Illuminate\Support\Facades\DB;

class UpdateApiClient
{
    /**
     * Existing tokens keep the scopes they were issued with, so they are revoked when a scope is taken away.
     *
     * @param  list<string>  $scopes
     */
    public function handle(ApiClient $client, string $name, array $scopes, int $responsibleUserId): void
    {
        DB::transaction(function () use ($client, $name, $scopes, $responsibleUserId) {
            $scopeRemoved = array_diff($client->scopes, $scopes) !== [];

            $client->update([
                'name' => $name,
                'scopes' => $scopes,
                'responsible_user_id' => $responsibleUserId,
            ]);

            if ($scopeRemoved) {
                $client->revokeTokens();
            }
        });
    }
}
