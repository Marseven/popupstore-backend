<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Prevent N+1 queries in non-production
        Model::preventLazyLoading(!$this->app->isProduction());

        // Sanctum token expiration (minutes)
        Sanctum::$personalAccessTokenExpiration = env('SANCTUM_TOKEN_EXPIRATION')
            ? now()->addMinutes((int) env('SANCTUM_TOKEN_EXPIRATION'))
            : null;
    }
}
