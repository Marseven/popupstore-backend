<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    private const HEADER = 'X-Correlation-ID';

    /**
     * Handle an incoming request.
     *
     * Check for an existing correlation ID header or generate a new UUID.
     * Store it in request attributes, share it with the log context,
     * and append it to the outgoing response headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header(self::HEADER) ?: (string) Str::uuid();

        $request->attributes->set('correlation_id', $correlationId);

        Log::shareContext(['correlation_id' => $correlationId]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
