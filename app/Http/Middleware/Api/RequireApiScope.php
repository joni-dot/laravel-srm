<?php

namespace App\Http\Middleware\Api;

use App\Enums\ApiScope;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Exceptions\MissingScopeException;
use Symfony\Component\HttpFoundation\Response;

class RequireApiScope
{
    public static function for(ApiScope $scope): string
    {
        return self::class.':'.$scope->value;
    }

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = $request->attributes->get(AccessToken::class);

        if (! $token instanceof AccessToken || $token->cant($scope)) {
            throw new MissingScopeException($scope);
        }

        return $next($request);
    }
}
