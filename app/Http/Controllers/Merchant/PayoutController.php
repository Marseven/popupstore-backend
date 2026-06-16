<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __construct(private MerchantService $merchants) {}

    public function index(Request $request): JsonResponse
    {
        $payouts = $this->merchants->scopedPayouts($request->user())
            ->with(['collection:id,name', 'order:id,order_number'])
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 20), 50));

        return response()->json($payouts);
    }
}
