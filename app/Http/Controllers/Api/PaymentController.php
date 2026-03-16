<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiatePaymentRequest;
use App\Models\Order;
use App\Services\EbillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private EbillingService $ebillingService) {}

    /**
     * Initiate a payment (create a bill) for an order.
     */
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $sessionId = $request->header('X-Session-Id');

        $query = Order::where('order_number', $validated['order_number']);

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id')
                ->where(function ($q) use ($sessionId, $validated) {
                    $q->where('session_id', $sessionId)
                      ->orWhere('guest_phone', $validated['phone']);
                });
        }

        $order = $query->firstOrFail();

        if ($order->payment_status === 'success') {
            return response()->json([
                'message' => 'Cette commande est déjà payée',
            ], 422);
        }

        $result = $this->ebillingService->createBill($order, $validated['phone']);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Handle Ebilling webhook callback.
     */
    public function callback(Request $request): JsonResponse
    {
        $this->ebillingService->handleCallback($request->all());

        return response()->json(['status' => 'ok'], 200);
    }
}
