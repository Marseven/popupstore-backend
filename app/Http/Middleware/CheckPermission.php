<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $request->user()->loadMissing('role.permissions');

        if (!$request->user()->hasPermission($permission)) {
            return response()->json(['message' => 'Permission insuffisante'], 403);
        }

        return $next($request);
    }
}
