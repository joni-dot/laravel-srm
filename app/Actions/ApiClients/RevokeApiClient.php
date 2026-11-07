<?php

namespace App\Actions\ApiClients;

use App\Models\ApiClient;
use Illuminate\Support\Facades\DB;

class RevokeApiClient
{
    public function handle(ApiClient $client): void
    {
        DB::transaction(function () use ($client) {
            $client->update(['revoked' => true]);
            $client->revokeTokens();
        });
    }
}
