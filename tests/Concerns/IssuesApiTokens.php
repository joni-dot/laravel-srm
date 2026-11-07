<?php

namespace Tests\Concerns;

use App\Actions\ApiClients\CreateApiClient;
use App\Enums\ApiScope;
use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Support\Once;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs tokens with a key pair made in memory, so no key file is needed in the repository or in CI.
 */
trait IssuesApiTokens
{
    /** @var array{private: string, public: string}|null */
    private static ?array $passportKeys = null;

    protected function setUpPassportKeys(): void
    {
        self::$passportKeys ??= $this->generateKeyPair();

        config([
            'passport.private_key' => self::$passportKeys['private'],
            'passport.public_key' => self::$passportKeys['public'],
        ]);
    }

    /**
     * @param  list<ApiScope>  $scopes
     */
    protected function createApiClient(array $scopes = [ApiScope::SuppliersRead]): ApiClient
    {
        return app(CreateApiClient::class)->handle(
            'Test integration',
            array_map(fn (ApiScope $scope) => $scope->value, $scopes),
            User::factory()->admin()->create()->id,
        );
    }

    /**
     * @param  array<string, string>  $extra
     * @return TestResponse<Response>
     */
    protected function requestToken(string $clientId, ?string $secret, array $extra = [])
    {
        return $this->post(route('api.oauth.token'), [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $secret,
            ...$extra,
        ]);
    }

    protected function accessTokenFor(ApiClient $client, ?string $scope = null): string
    {
        $response = $this->requestToken($client->id, $client->plainSecret, $scope === null ? [] : ['scope' => $scope])
            ->assertOk();

        return $response->json('access_token');
    }

    /**
     * Passport remembers a client for the rest of a request. A test sends many requests through
     * one application, so after changing a client the remembered copy must be dropped.
     */
    protected function forgetRememberedClients(): void
    {
        Once::flush();
    }

    /**
     * @return array{private: string, public: string}
     */
    private function generateKeyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        return ['private' => $privateKey, 'public' => openssl_pkey_get_details($key)['key']];
    }
}
