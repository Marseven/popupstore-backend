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
        $user = $request->user();
        $sessionId = $request->header('X-Session-Id');

        $rules = [
            'shipping_name' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'customer_notes' => 'nullable|string|max:1000',
        ];

        if (!$user) {
            $rules['guest_phone'] = 'required|string|max:20';
            $rules['guest_email'] = 'nullable|email|max:255';
        }

        $validated = $request->validate($rules);

        // Get cart items (auth or guest)
        $cartQuery = CartItem::with(['product.images', 'product.mediaContent', 'size']);

        if ($user) {
            $cartQuery->where('user_id', $user->id);
        } elseif ($sessionId) {
            $cartQuery->whereNull('user_id')->where('session_id', $sessionId);
        } else {
            return response()->json([
                'message' => 'Session invalide. Veuillez réessayer.',
            ], 422);
        }

        $cartItems = $cartQuery->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Votre panier est vide',
            ], 422);
        }

        try {
            $order = DB::transaction(function () use ($validated, $user, $sessionId, $cartItems) {
                // Calculate totals
                $subtotal = $cartItems->sum(fn($item) => $item->product->price * $item->quantity);
                $shippingFee = 0; // Can be configured later
                $total = $subtotal + $shippingFee;

                // Create order
                $order = Order::create([
                    'user_id' => $user?->id,
                    'guest_phone' => $validated['guest_phone'] ?? null,
                    'guest_email' => $validated['guest_email'] ?? null,
                    'session_id' => $user ? null : $sessionId,
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
                if ($user) {
                    CartItem::where('user_id', $user->id)->delete();
                } else {
                    CartItem::whereNull('user_id')->where('session_id', $sessionId)->delete();
                }

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
        $user = $request->user();
        $sessionId = $request->header('X-Session-Id');

        $query = Order::where('order_number', $orderNumber)
            ->with(['items', 'transactions']);

        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($sessionId) {
            $query->whereNull('user_id')->where('session_id', $sessionId);
        } else {
            abort(404);
        }

        $order = $query->firstOrFail();

        return response()->json([
            'order' => $order,
        ]);
    }

    /**
     * Track a guest order by phone + order number.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'order_number' => 'required|string|max:50',
        ]);

        $order = Order::where('order_number', $validated['order_number'])
            ->where(function ($q) use ($validated) {
                $q->where('guest_phone', $validated['phone'])
                  ->orWhere('shipping_phone', $validated['phone']);
            })
            ->with(['items', 'transactions'])
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Aucune commande trouvée avec ces informations',
            ], 404);
        }

        return response()->json([
            'order' => $order,
        ]);
    }
}
