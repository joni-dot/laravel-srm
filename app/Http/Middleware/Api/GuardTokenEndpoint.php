<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks an address for a while after repeated failed token requests, and logs every attempt.
 *
 * The block is per address, not per client id: blocking by client id would let anyone who
 * knows an id lock the real system out by sending wrong secrets.
 */
class GuardTokenEndpoint
{
    public const int MAX_FAILURES = 10;

    public const int BLOCK_SECONDS = 15 * 60;

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'oauth-token-failures:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILURES)) {
            $this->log('warning', 'Blocked token request after repeated failures', $request, Response::HTTP_TOO_MANY_REQUESTS);

            return response()->json(
                ['error' => 'too_many_attempts', 'message' => 'Too many failed attempts. Try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => RateLimiter::availableIn($key)],
            );
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            $this->log('info', 'Access token issued', $request, $response->getStatusCode());
        } else {
            RateLimiter::hit($key, self::BLOCK_SECONDS);
            $this->log('warning', 'Failed token request', $request, $response->getStatusCode());
        }

        return $response;
    }

    private function log(string $level, string $message, Request $request, int $status): void
    {
        Log::channel('api')->log($level, $message, [
            'client_id' => Str::limit((string) ($request->getUser() ?? $request->input('client_id')), 64),
            'grant_type' => Str::limit((string) $request->input('grant_type'), 64),
            'status' => $status,
            'ip' => $request->ip(),
        ]);
    }
}
