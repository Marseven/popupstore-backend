<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replays the stored response for a repeated Idempotency-Key, so a network
 * retry of a non-idempotent POST (e.g. payment initiation) cannot double-charge.
 *
 * Keyed by header + route + authenticated user/session, cached 24h. The header
 * is optional: requests without it are processed normally (no dedup).
 */
class IdempotencyKey
{
    private const TTL = 86400; // 24h

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $cacheKey = $this->cacheKey($request, $key);

        if ($cached = Cache::get($cacheKey)) {
            return response($cached['body'], $cached['status'])
                ->withHeaders(['Content-Type' => 'application/json', 'Idempotent-Replay' => 'true']);
        }

        $response = $next($request);

        // Only memoize successful, deterministic outcomes.
        if ($response->getStatusCode() < 400) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ], self::TTL);
        }

        return $response;
    }

    private function cacheKey(Request $request, string $key): string
    {
        $scope = $request->user()?->id
            ? 'u:'.$request->user()->id
            : 's:'.($request->header('X-Session-Id') ?: $request->ip());

        return 'idem:'.sha1($request->path().'|'.$scope.'|'.$key);
    }
}
