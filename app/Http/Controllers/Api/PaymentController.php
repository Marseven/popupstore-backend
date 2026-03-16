<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\EbillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private EbillingService $ebillingService) {}

    /**
     * Initiate a payment (create a bill) for an order.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string|exists:orders,order_number',
            'provider' => 'required|string|in:airtel,moov',
            'phone' => 'required|string|max:20',
        ]);

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

        $result = $this->ebillingService->createBill(
            $order,
            $validated['provider'],
            $validated['phone']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Send a USSD push for an existing bill.
     */
    public function ussdPush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bill_id' => 'required|string',
            'phone' => 'required|string|max:20',
            'provider' => 'required|string|in:airtel,moov',
        ]);

        $transaction = PaymentTransaction::where('transaction_id', $validated['bill_id'])->first();

        if (!$transaction) {
            return response()->json(['message' => 'Facture introuvable'], 404);
        }

        $order = $transaction->order;
        $user = $request->user();
        $sessionId = $request->header('X-Session-Id');

        if ($user) {
            if ($order->user_id !== $user->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }
        } else {
            if ($order->session_id !== $sessionId && $order->guest_phone !== $validated['phone']) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }
        }

        if ($transaction->status === 'success') {
            return response()->json(['message' => 'Cette facture est déjà payée'], 422);
        }

        $result = $this->ebillingService->ussdPush(
            $validated['bill_id'],
            $validated['phone'],
            $validated['provider']
        );

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
