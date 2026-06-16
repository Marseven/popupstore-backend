<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private MerchantService $merchants) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->merchants->scopedOrders($request->user())
            ->with('items')
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 20), 50));

        return response()->json($orders);
    }
}
