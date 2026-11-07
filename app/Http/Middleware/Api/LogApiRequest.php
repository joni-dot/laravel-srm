<?php

namespace App\Http\Middleware\Api;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a record of who read what through the API, including refused requests.
 */
class LogApiRequest
{
    /**
     * A client might put a credential in the address by mistake; it must not end up in the log.
     */
    private const array HIDDEN_QUERY_KEYS = ['access_token', 'client_secret', 'token'];

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        $response = $next($request);

        Log::channel('api')->info('API request', [
            'client_id' => $request->attributes->get(ApiClient::class)?->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => Arr::except($request->query(), self::HIDDEN_QUERY_KEYS),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
        ]);

        return $response;
    }
}
