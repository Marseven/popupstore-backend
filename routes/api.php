<?php

use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\MerchantController as AdminMerchantController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentTransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\PushSubscriptionController as AdminPushController;
use App\Http\Controllers\Admin\RevenueShareController as AdminRevenueShareController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\ShippingZoneController as AdminShippingZoneController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MediaContentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Merchant\MerchantController;
use App\Http\Controllers\Merchant\OrderController as MerchantOrderController;
use App\Http\Controllers\Merchant\PayoutController as MerchantPayoutController;
use App\Http\Controllers\Merchant\ProductController as MerchantProductController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

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
Route::middleware('auth.optional')->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
});

// Payment callback (webhook - no Sanctum auth, but shared-secret verified)
Route::post('/payments/callback', [PaymentController::class, 'callback'])
    ->middleware('ebilling.webhook');

// Shipping (public)
Route::get('/shipping/cities', [ShippingController::class, 'cities']);

// Order tracking (public - guest access by phone + order number)
Route::get('/orders/track', [OrderController::class, 'track']);

// Campaigns (public)
Route::get('/campaigns/{slug}', [CampaignController::class, 'show']);
Route::get('/campaigns/{slug}/leaderboard', [CampaignController::class, 'leaderboard']);
Route::get('/campaigns/{slug}/teams', [CampaignController::class, 'teams']);

/*
|--------------------------------------------------------------------------
| Optional Auth Routes (authenticated or guest)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.optional')->group(function () {
    // Orders (create & view single)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show'])->where('orderNumber', 'POP-[^/]+');

    // Payments — dedicated throttle + Idempotency-Key replay (anti double-charge)
    Route::post('/payments/initiate', [PaymentController::class, 'initiate'])
        ->middleware(['throttle:payments', 'idempotency']);

    // Campaign entitlements for a buyer's order (guest or authenticated)
    Route::get('/orders/{orderNumber}/entitlements', [CampaignController::class, 'entitlements'])
        ->where('orderNumber', 'POP-[^/]+');
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
    | Merchant Routes (self-service)
    |--------------------------------------------------------------------------
    */

    // Any authenticated user may apply to become a merchant.
    Route::post('/merchant/apply', [MerchantController::class, 'apply']);

    // Owner-scoped merchant area — requires the merchant role AND an approved profile.
    Route::prefix('merchant')->middleware(['role:merchant', 'merchant.approved'])->group(function () {
        Route::get('/dashboard', [MerchantController::class, 'dashboard']);
        Route::get('/products', [MerchantProductController::class, 'index']);
        Route::post('/products', [MerchantProductController::class, 'store']);
        Route::put('/products/{id}', [MerchantProductController::class, 'update']);
        Route::delete('/products/{id}', [MerchantProductController::class, 'destroy']);
        Route::get('/orders', [MerchantOrderController::class, 'index']);
        Route::get('/payouts', [MerchantPayoutController::class, 'index']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->middleware('role:super_admin,manager')->group(function () {
        // Notifications
        Route::get('/notifications', [AdminNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [AdminNotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [AdminNotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{id}/read', [AdminNotificationController::class, 'markAsRead']);

        // Push subscriptions
        Route::post('/push/subscribe', [AdminPushController::class, 'subscribe']);
        Route::post('/push/unsubscribe', [AdminPushController::class, 'unsubscribe']);

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

        // Transactions (payments)
        Route::get('/transactions', [AdminTransactionController::class, 'index']);

        // Payouts (revenue split) — managers may consult the list
        Route::get('/payouts', [AdminPayoutController::class, 'index']);

        // Campaigns management
        Route::get('/campaigns', [AdminCampaignController::class, 'index']);
        Route::post('/campaigns', [AdminCampaignController::class, 'store']);
        Route::get('/campaigns/{id}', [AdminCampaignController::class, 'show']);
        Route::put('/campaigns/{id}', [AdminCampaignController::class, 'update']);
        Route::post('/campaigns/{id}/close', [AdminCampaignController::class, 'close']);
        Route::post('/campaigns/{id}/teams', [AdminCampaignController::class, 'storeTeam']);

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

        // Revenue shares & payout settlement (super_admin only)
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/collections/{collection}/revenue-shares', [AdminRevenueShareController::class, 'index']);
            Route::post('/collections/{collection}/revenue-shares', [AdminRevenueShareController::class, 'store']);
            Route::put('/collections/{collection}/revenue-shares/{id}', [AdminRevenueShareController::class, 'update']);
            Route::delete('/collections/{collection}/revenue-shares/{id}', [AdminRevenueShareController::class, 'destroy']);
            Route::post('/payouts/{id}/mark-paid', [AdminPayoutController::class, 'markPaid']);

            // Collections picker (revenue-share configuration, etc.)
            Route::get('/collections', function () {
                return response()->json([
                    'collections' => \App\Models\Collection::query()
                        ->select('id', 'name', 'type', 'owner_user_id')
                        ->orderBy('name')
                        ->get(),
                ]);
            });

            // Merchant enrolment management
            Route::get('/merchants', [AdminMerchantController::class, 'index']);
            Route::post('/merchants', [AdminMerchantController::class, 'store']);
            Route::post('/merchants/{id}/approve', [AdminMerchantController::class, 'approve']);
            Route::post('/merchants/{id}/suspend', [AdminMerchantController::class, 'suspend']);
        });
    });
});
