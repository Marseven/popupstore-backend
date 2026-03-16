<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\MediaContentController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Admin\ShippingZoneController as AdminShippingZoneController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', HealthController::class);

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Auth (rate limited: 5 attempts/min)
Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Products (public)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/category/{slug}', [ProductController::class, 'byCategory']);
Route::get('/products/collection/{slug}', [ProductController::class, 'byCollection']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Categories (public, cached 5min)
Route::get('/categories', function () {
    $categories = Cache::remember('categories.active', 300, function () {
        return \App\Models\ProductCategory::active()
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();
    });

    return response()->json(['categories' => $categories]);
});

// Collections (public)
Route::get('/collections', [CollectionController::class, 'index']);
Route::get('/collections/{slug}', [CollectionController::class, 'show']);

// Media (public - accessed via QR scan)
Route::get('/media', [MediaContentController::class, 'index']);
Route::get('/media/videos', [MediaContentController::class, 'videos']);
Route::get('/media/audios', [MediaContentController::class, 'audios']);
Route::get('/media/{uuid}', [MediaContentController::class, 'show']);
Route::get('/media/{uuid}/stream', [MediaContentController::class, 'stream'])->name('media.stream');

// Cart (public - uses session or auth)
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'store']);
Route::put('/cart/{id}', [CartController::class, 'update']);
Route::delete('/cart/{id}', [CartController::class, 'destroy']);
Route::delete('/cart', [CartController::class, 'clear']);

// Payment callback (webhook - no auth)
Route::post('/payments/callback', [PaymentController::class, 'callback']);

// Shipping (public)
Route::get('/shipping/cities', [ShippingController::class, 'cities']);
Route::post('/shipping/calculate', [ShippingController::class, 'calculateFee']);

// Order tracking (public - guest access by phone + order number)
Route::get('/orders/track', [OrderController::class, 'track']);

/*
|--------------------------------------------------------------------------
| Optional Auth Routes (authenticated or guest)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.optional')->group(function () {
    // Orders (create & view single)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show'])->where('orderNumber', 'POP-.*');

    // Payments
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    // Orders (history - authenticated only)
    Route::get('/orders', [OrderController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->middleware('role:super_admin,manager')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Products management
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::get('/products/{id}', [AdminProductController::class, 'show']);
        Route::put('/products/{id}', [AdminProductController::class, 'update']);
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
        Route::put('/products/{id}/stock', [AdminProductController::class, 'updateStock']);

        // Shipping zones management
        Route::get('/shipping-zones', [AdminShippingZoneController::class, 'index']);
        Route::post('/shipping-zones', [AdminShippingZoneController::class, 'store']);
        Route::get('/shipping-zones/{id}', [AdminShippingZoneController::class, 'show']);
        Route::put('/shipping-zones/{id}', [AdminShippingZoneController::class, 'update']);
        Route::delete('/shipping-zones/{id}', [AdminShippingZoneController::class, 'destroy']);

        // Orders management
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
        Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/note', [AdminOrderController::class, 'addNote']);

        // Media management
        Route::get('/media', [AdminMediaController::class, 'index']);
        Route::post('/media', [AdminMediaController::class, 'store']);
        Route::get('/media/{id}', [AdminMediaController::class, 'show']);
        Route::put('/media/{id}', [AdminMediaController::class, 'update']);
        Route::delete('/media/{id}', [AdminMediaController::class, 'destroy']);
        Route::get('/media/{id}/qr-download', [AdminMediaController::class, 'downloadQr']);
        Route::post('/media/{id}/qr-regenerate', [AdminMediaController::class, 'regenerateQr']);

        // Users management (super_admin only)
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::get('/users/{id}', [AdminUserController::class, 'show']);
            Route::put('/users/{id}', [AdminUserController::class, 'update']);
            Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
        });

        // Roles & Permissions management (super_admin only)
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/roles', [AdminRoleController::class, 'index']);
            Route::post('/roles', [AdminRoleController::class, 'store']);
            Route::get('/roles/{id}', [AdminRoleController::class, 'show']);
            Route::put('/roles/{id}', [AdminRoleController::class, 'update']);
            Route::delete('/roles/{id}', [AdminRoleController::class, 'destroy']);
            Route::get('/permissions', [AdminPermissionController::class, 'index']);
        });

        // Settings (super_admin only)
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/settings', [AdminSettingController::class, 'index']);
            Route::put('/settings', [AdminSettingController::class, 'update']);
            Route::post('/settings/logo', [AdminSettingController::class, 'uploadLogo']);
        });
    });
});
