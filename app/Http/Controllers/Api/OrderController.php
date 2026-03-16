<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Create order from cart items.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->header('X-Session-Id');
        $validated = $request->validated();

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

                // Dynamic shipping fee from selected zone
                $shippingFee = 0;
                $shippingZoneName = null;

                if (!empty($validated['shipping_zone_id'])) {
                    $zone = ShippingZone::find($validated['shipping_zone_id']);
                    if ($zone) {
                        $shippingFee = (float) $zone->fee;
                        $shippingZoneName = $zone->name;
                    }
                }

                // Free shipping threshold
                $freeThreshold = (float) Setting::get('free_shipping_threshold', 0);
                if ($freeThreshold > 0 && $subtotal >= $freeThreshold) {
                    $shippingFee = 0;
                }

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
                    'shipping_address' => $validated['shipping_address'] ?? 'Retrait en boutique',
                    'shipping_city' => $validated['shipping_city'] ?? 'Retrait en boutique',
                    'shipping_quartier' => $validated['shipping_quartier'] ?? null,
                    'shipping_zone' => $shippingZoneName,
                    'payment_method' => $validated['payment_method'] ?? null,
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

            OrderCreated::dispatch($order);

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
            ->with(['items.product.images', 'transactions']);

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
