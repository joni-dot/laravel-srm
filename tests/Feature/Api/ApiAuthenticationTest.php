<?php

namespace Tests\Feature\Api;

use App\Actions\ApiClients\RegenerateApiClientSecret;
use App\Actions\ApiClients\RevokeApiClient;
use App\Actions\ApiClients\UpdateApiClient;
use App\Http\Middleware\Api\GuardTokenEndpoint;
use App\Models\ApiClient;
use App\Models\Supplier;
use App\Models\User;
use App\Providers\ApiServiceProvider;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\Concerns\IssuesApiTokens;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use IssuesApiTokens;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPassportKeys();
    }

    public function test_every_api_endpoint_needs_a_token()
    {
        $supplier = Supplier::factory()->create();

        foreach ([route('api.v1.suppliers.index'), route('api.v1.suppliers.show', $supplier)] as $url) {
            $this->getJson($url)->assertUnauthorized();
            $this->getJson($url, ['Authorization' => 'Bearer not-a-token'])->assertUnauthorized();
        }
    }

    public function test_callers_without_a_token_cannot_tell_which_suppliers_exist()
    {
        $this->getJson(route('api.v1.suppliers.show', 999999))->assertUnauthorized();
    }

    public function test_a_logged_in_person_cannot_use_the_api_with_their_session()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('api.v1.suppliers.index'))
            ->assertUnauthorized();
    }

    public function test_a_client_trades_its_secret_for_a_short_lived_token()
    {
        $client = $this->createApiClient();

        $this->requestToken($client->id, $client->plainSecret)
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', ApiServiceProvider::ACCESS_TOKEN_MINUTES * 60)
            ->assertJsonMissingPath('refresh_token');

        $this->getJson(route('api.v1.suppliers.index'), ['Authorization' => 'Bearer '.$this->accessTokenFor($client)])
            ->assertOk();
    }

    public function test_the_secret_is_stored_only_as_a_hash()
    {
        $client = $this->createApiClient();

        $this->assertNotSame($client->plainSecret, ApiClient::find($client->id)->getRawOriginal('secret'));
        $this->assertSame(40, strlen((string) $client->plainSecret));
    }

    public function test_a_wrong_secret_or_unknown_client_gets_no_token()
    {
        $client = $this->createApiClient();

        $this->requestToken($client->id, 'wrong-secret')->assertUnauthorized()->assertJsonMissingPath('access_token');
        $this->requestToken('9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', 'anything')->assertUnauthorized();
        $this->requestToken($client->id, null)->assertBadRequest()->assertJsonMissingPath('access_token');
    }

    public function test_only_the_client_credentials_grant_is_offered()
    {
        $client = $this->createApiClient();

        foreach (['password', 'authorization_code', 'refresh_token', 'implicit', 'urn:ietf:params:oauth:grant-type:device_code', 'personal_access'] as $grant) {
            $response = $this->requestToken($client->id, $client->plainSecret, [
                'grant_type' => $grant, 'username' => 'a', 'password' => 'b', 'code' => 'c', 'refresh_token' => 'd', 'device_code' => 'e',
            ]);

            $this->assertContains($response->getStatusCode(), [400, 401], "Grant {$grant} must be refused");
            $response->assertJsonMissingPath('access_token');
        }
    }

    public function test_passports_login_based_routes_are_not_registered()
    {
        $this->get('/oauth/authorize')->assertNotFound();
        $this->post('/oauth/token')->assertNotFound();
        $this->get('/oauth/device')->assertNotFound();
    }

    /**
     * Token expiry is checked against the real clock, so the test uses a one-second token instead of moving time.
     */
    public function test_an_expired_token_is_refused()
    {
        Passport::clientCredentialsTokensExpireIn(CarbonInterval::second());
        $token = $this->accessTokenFor($this->createApiClient());

        sleep(2);

        $this->getJson(route('api.v1.suppliers.index'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_revoking_a_client_stops_its_tokens_and_new_token_requests()
    {
        $client = $this->createApiClient();
        $token = $this->accessTokenFor($client);

        app(RevokeApiClient::class)->handle($client);
        $this->forgetRememberedClients();

        $this->getJson(route('api.v1.suppliers.index'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
        $this->requestToken($client->id, $client->plainSecret)->assertUnauthorized();
    }

    public function test_a_revoked_client_is_refused_even_if_a_token_was_somehow_left_active()
    {
        $client = $this->createApiClient();
        $token = $this->accessTokenFor($client);

        $client->update(['revoked' => true]);

        $this->getJson(route('api.v1.suppliers.index'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_a_new_secret_stops_the_old_secret_and_its_tokens()
    {
        $client = $this->createApiClient();
        $oldSecret = $client->plainSecret;
        $oldToken = $this->accessTokenFor($client);

        $newSecret = app(RegenerateApiClientSecret::class)->handle($client);
        $this->forgetRememberedClients();

        $this->getJson(route('api.v1.suppliers.index'), ['Authorization' => "Bearer {$oldToken}"])->assertUnauthorized();
        $this->requestToken($client->id, $oldSecret)->assertUnauthorized();
        $this->requestToken($client->id, $newSecret)->assertOk();
    }

    public function test_a_client_without_the_scope_is_forbidden()
    {
        $client = $this->createApiClient([]);

        $this->getJson(route('api.v1.suppliers.index'), ['Authorization' => 'Bearer '.$this->accessTokenFor($client)])
            ->assertForbidden();
    }

    public function test_asking_for_every_scope_gives_only_the_allowed_ones()
    {
        $client = $this->createApiClient([]);

        $this->getJson(route('api.v1.suppliers.index'), ['Authorization' => 'Bearer '.$this->accessTokenFor($client, '*')])
            ->assertForbidden();
    }

    public function test_taking_a_scope_away_revokes_tokens_that_had_it()
    {
        $client = $this->createApiClient();
        $token = $this->accessTokenFor($client);

        app(UpdateApiClient::class)->handle($client, $client->name, [], (int) $client->responsible_user_id);

        $this->getJson(route('api.v1.suppliers.index'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_an_address_is_blocked_after_repeated_failed_token_requests()
    {
        $client = $this->createApiClient();

        for ($attempt = 0; $attempt < GuardTokenEndpoint::MAX_FAILURES; $attempt++) {
            $this->requestToken($client->id, 'wrong-secret')->assertUnauthorized();
        }

        $this->requestToken($client->id, $client->plainSecret)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_web_pages_on_other_sites_cannot_call_the_api_from_a_browser()
    {
        $this->call('OPTIONS', route('api.v1.suppliers.index'), server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');

        $this->post(route('api.oauth.token'), [], ['Origin' => 'https://evil.example'])
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_api_responses_are_never_cached()
    {
        $response = $this->getJson(route('api.v1.suppliers.index'), ['Authorization' => 'Bearer '.$this->accessTokenFor($this->createApiClient())]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_api_calls_are_logged_with_the_client_but_without_credentials()
    {
        $client = $this->createApiClient();
        $token = $this->accessTokenFor($client);
        $log = Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('api')->andReturn($log);

        $this->getJson(route('api.v1.suppliers.index', ['status' => 'active', 'access_token' => 'leaked']), ['Authorization' => "Bearer {$token}"])
            ->assertOk();

        $log->shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'API request'
            && $context['client_id'] === $client->id
            && $context['status'] === 200
            && $context['query'] === ['status' => 'active']
            && ! str_contains(json_encode($context), $token));
    }

    public function test_failed_token_requests_are_logged_without_the_secret()
    {
        $client = $this->createApiClient();
        $log = Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('api')->andReturn($log);

        $this->requestToken($client->id, 'wrong-secret-value')->assertUnauthorized();

        $log->shouldHaveReceived('log')->withArgs(fn (string $level, string $message, array $context) => $level === 'warning'
            && $context['client_id'] === $client->id
            && ! str_contains(json_encode($context), 'wrong-secret-value'));
    }

    public function test_last_use_is_recorded_without_writing_on_every_call()
    {
        $client = $this->createApiClient();
        $headers = ['Authorization' => 'Bearer '.$this->accessTokenFor($client)];

        $this->getJson(route('api.v1.suppliers.index'), $headers)->assertOk();
        $firstUse = $client->refresh()->last_used_at;
        $this->assertNotNull($firstUse);

        $this->travel(1)->minute();
        $this->getJson(route('api.v1.suppliers.index'), $headers)->assertOk();
        $this->assertTrue($firstUse->equalTo($client->refresh()->last_used_at));
    }
}
