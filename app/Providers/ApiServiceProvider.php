<?php

namespace App\Providers;

use App\Enums\ApiScope;
use App\Models\ApiClient;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

/**
 * The API only serves other systems through the OAuth client credentials grant.
 * Every other way of getting a token is switched off.
 */
class ApiServiceProvider extends ServiceProvider
{
    public const int ACCESS_TOKEN_MINUTES = 15;

    public const int REQUESTS_PER_MINUTE = 120;

    public function register(): void
    {
        // Passport's own routes include login-based flows this app does not offer; routes/api.php adds only the token endpoint.
        Passport::ignoreRoutes();
    }

    public function boot(): void
    {
        Passport::$deviceCodeGrantEnabled = false;
        Passport::useClientModel(ApiClient::class);
        Passport::tokensCan(ApiScope::descriptions());
        // A client that asks for no scope gets all scopes an admin allowed for it, and never more.
        Passport::setDefaultScope(array_keys(ApiScope::descriptions()));
        Passport::clientCredentialsTokensExpireIn(CarbonInterval::minutes(self::ACCESS_TOKEN_MINUTES));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(self::REQUESTS_PER_MINUTE)
            ->by('api-client:'.$request->attributes->get(ApiClient::class)?->id));
    }
}
