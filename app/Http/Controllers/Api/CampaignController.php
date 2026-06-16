<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use App\Models\Order;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(private CampaignService $campaigns) {}

    public function show(string $slug): JsonResponse
    {
        $campaign = Campaign::where('slug', $slug)->firstOrFail();

        return response()->json([
            'campaign' => $campaign,
            'teams' => $campaign->teams()->orderBy('sort_order')->get(),
        ]);
    }

    public function leaderboard(string $slug): JsonResponse
    {
        $campaign = Campaign::where('slug', $slug)->firstOrFail();

        return response()->json(['leaderboard' => $this->campaigns->leaderboard($campaign)]);
    }

    public function teams(string $slug): JsonResponse
    {
        $campaign = Campaign::where('slug', $slug)->firstOrFail();

        return response()->json(['teams' => $campaign->teams()->orderBy('sort_order')->get()]);
    }

    /**
     * Entitlements (codes + finale ticket) for a buyer's order.
     * Scoped like OrderController::show — owner (user) or matching session.
     */
    public function entitlements(Request $request, string $orderNumber): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->header('X-Session-Id');

        $query = Order::where('order_number', $orderNumber);
        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($sessionId) {
            $query->whereNull('user_id')->where('session_id', $sessionId);
        } else {
            abort(404);
        }

        $order = $query->firstOrFail();

        return response()->json([
            'entitlements' => CampaignEntitlement::with('team:id,name,slug,artist_name')
                ->where('order_id', $order->id)
                ->get(),
        ]);
    }
}
