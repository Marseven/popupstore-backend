<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function __construct(private MerchantService $merchants) {}

    /** Submit a merchant application (any authenticated user). */
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'rccm_nif' => 'nullable|string|max:100',
            'payout_phone' => 'required|string|max:20',
            'payout_provider' => 'required|string|in:airtelmoney,moovmoney4',
        ]);

        $profile = $this->merchants->apply($request->user(), $validated);

        return response()->json(['merchant_profile' => $profile], 201);
    }

    /** Scoped dashboard counts for the approved merchant. */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'collections' => $user->ownedCollections()->count(),
            'products' => $this->merchants->scopedProducts($user)->count(),
            'orders' => $this->merchants->scopedOrders($user)->count(),
            'payouts_pending' => $this->merchants->scopedPayouts($user)->where('status', 'pending')->count(),
        ]);
    }
}
