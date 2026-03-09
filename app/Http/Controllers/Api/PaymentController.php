<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EbillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private EbillingService $ebillingService) {}

    /**
     * Initiate a payment for an order.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string|exists:orders,order_number',
            'provider' => 'required|string|in:airtel,moov',
            'phone' => 'required|string|max:20',
        ]);

        $order = Order::where('order_number', $validated['order_number'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($order->payment_status === 'success') {
            return response()->json([
                'message' => 'Cette commande est déjà payée',
            ], 422);
        }

        $result = $this->ebillingService->initiatePayment(
            $order,
            $validated['provider'],
            $validated['phone']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Check payment status.
     */
    public function status(string $transactionId): JsonResponse
    {
        $result = $this->ebillingService->checkStatus($transactionId);

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
