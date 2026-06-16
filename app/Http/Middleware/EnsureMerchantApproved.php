<?php

namespace App\Http\Middleware;

use App\Enums\MerchantStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate merchant-scoped routes: the user must have an approved merchant profile.
 * Suspended or pending merchants (even if they still hold the role) are blocked.
 */
class EnsureMerchantApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user()?->merchantProfile;

        if (! $profile || $profile->status !== MerchantStatus::Approved) {
            return response()->json(['message' => 'Compte marchand non approuvé'], 403);
        }

        return $next($request);
    }
}
