<?php

namespace App\Actions\ApiClients;

use App\Models\ApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegenerateApiClientSecret
{
    /**
     * A new secret is usually issued because the old one may have leaked, so tokens got with it stop working too.
     *
     * @return string the new secret, which is not stored anywhere in readable form
     */
    public function handle(ApiClient $client): string
    {
        $secret = Str::random(CreateApiClient::SECRET_LENGTH);

        DB::transaction(function () use ($client, $secret) {
            $client->update(['secret' => $secret]);
            $client->revokeTokens();
        });

        return $secret;
    }
}
