<?php

use App\Exceptions\BusinessException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Versioned alias: expose every API route under /api/v1 as well.
            // Legacy /api/* stays available (deprecation window). The v1. name
            // prefix avoids route-name collisions (e.g. v1.media.stream).
            \Illuminate\Support\Facades\Route::middleware('api')
                ->prefix('api/v1')
                ->name('v1.')
                ->group(__DIR__.'/../routes/api.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'auth.optional' => \App\Http\Middleware\OptionalAuth::class,
            'idempotency' => \App\Http\Middleware\IdempotencyKey::class,
            'ebilling.webhook' => \App\Http\Middleware\VerifyEbillingWebhook::class,
        ]);
        $middleware->statefulApi();

        // Global middleware: CorrelationId on all API requests
        $middleware->api(append: [
            \App\Http\Middleware\CorrelationId::class,
        ]);
    })
    ->booted(function () {
        // Rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Dedicated limiter for the money path — absorbs campaign spikes without
        // throttling normal browsing (separate bucket from the global 60/min).
        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(12)->by($request->user()?->id ?: ($request->header('X-Session-Id') ?: $request->ip()));
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (BusinessException $e) {
            return $e->render();
        });
    })->create();
