<?php

namespace App\Actions\ApiClients;

use App\Models\ApiClient;
use Illuminate\Support\Str;

class CreateApiClient
{
    public const int SECRET_LENGTH = 40;

    /**
     * The returned client's plainSecret is the only copy of the secret; the database keeps a hash.
     *
     * @param  list<string>  $scopes
     */
    public function handle(string $name, array $scopes, int $responsibleUserId): ApiClient
    {
        return ApiClient::create([
            'name' => $name,
            'secret' => Str::random(self::SECRET_LENGTH),
            'scopes' => $scopes,
            'responsible_user_id' => $responsibleUserId,
            'grant_types' => [ApiClient::GRANT_TYPE],
            'redirect_uris' => [],
            'revoked' => false,
        ]);
    }
}
