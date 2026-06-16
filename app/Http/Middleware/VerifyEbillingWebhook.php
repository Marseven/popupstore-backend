<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the Ebilling payment callback (which has no Sanctum auth).
 *
 * Enforces a shared secret ONLY when `ebilling.callback_secret` is configured —
 * this lets the currently-deployed prod callback keep working until the secret
 * is set, after which forged/unauthenticated calls are rejected. The secret may
 * arrive as the `X-Callback-Token` header or a `callback_token` payload field
 * (exact mechanism to confirm with Ebilling).
 */
class VerifyEbillingWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('ebilling.callback_secret');

        if (! $expected) {
            Log::warning('Ebilling callback received without a configured secret (EBILLING_CALLBACK_SECRET unset)');

            return $next($request);
        }

        $provided = $request->header('X-Callback-Token') ?: $request->input('callback_token');

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            Log::warning('Ebilling callback rejected: invalid or missing secret', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
