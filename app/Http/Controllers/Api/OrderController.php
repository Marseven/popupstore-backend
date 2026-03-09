<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Create order from cart items.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_name' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'customer_notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        // Get cart items
        $cartItems = CartItem::with(['product.images', 'product.mediaContent', 'size'])
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Votre panier est vide',
            ], 422);
        }

        try {
            $order = DB::transaction(function () use ($validated, $user, $cartItems) {
                // Calculate totals
                $subtotal = $cartItems->sum(fn($item) => $item->product->price * $item->quantity);
                $shippingFee = 0; // Can be configured later
                $total = $subtotal + $shippingFee;

                // Create order
                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'shipping_fee' => $shippingFee,
                    'discount' => 0,
                    'total' => $total,
                    'shipping_name' => $validated['shipping_name'],
                    'shipping_phone' => $validated['shipping_phone'],
                    'shipping_address' => $validated['shipping_address'],
                    'shipping_city' => $validated['shipping_city'],
                    'payment_status' => 'pending',
                    'customer_notes' => $validated['customer_notes'] ?? null,
                ]);

                // Create order items with snapshot data
                foreach ($cartItems as $cartItem) {
                    $product = $cartItem->product;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'size_id' => $cartItem->size_id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'size_name' => $cartItem->size?->name,
                        'unit_price' => $product->price,
                        'quantity' => $cartItem->quantity,
                        'total' => $product->price * $cartItem->quantity,
                        'media_content_id' => $product->media_content_id,
                    ]);

                    // Decrement stock
                    if ($cartItem->size_id) {
                        ProductStock::where('product_id', $product->id)
                            ->where('size_id', $cartItem->size_id)
                            ->decrement('quantity', $cartItem->quantity);
                    }
                }

                // Clear cart
                CartItem::where('user_id', $user->id)->delete();

                return $order;
            });

            return response()->json([
                'message' => 'Commande créée avec succès',
                'order' => $order->load('items'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la commande',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List user's orders with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 50);

        $orders = Order::where('user_id', $request->user()->id)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * Get order details by order number.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->with(['items', 'transactions'])
            ->firstOrFail();

        return response()->json([
            'order' => $order,
        ]);
    }
}
