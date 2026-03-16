<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    /**
     * Get cart items.
     */
    public function index(Request $request): JsonResponse
    {
        [$userId, $sessionId] = $this->getIdentifiers($request);

        $cartData = $this->cartService->getTotal($userId, $sessionId);

        return response()->json([
            'items' => $cartData['items'],
            'subtotal' => $cartData['subtotal'],
            'item_count' => $cartData['item_count'],
        ]);
    }

    /**
     * Add item to cart.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'size_id' => 'nullable|integer|exists:sizes,id',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        [$userId, $sessionId] = $this->getIdentifiers($request);

        try {
            $item = $this->cartService->addItem(
                $userId,
                $sessionId,
                $validated['product_id'],
                $validated['size_id'] ?? null,
                $validated['quantity'] ?? 1
            );

            return response()->json([
                'message' => 'Produit ajouté au panier',
                'item' => $item->load(['product.images', 'size']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update cart item quantity.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        [$userId, $sessionId] = $this->getIdentifiers($request);

        try {
            $item = $this->cartService->updateQuantity(
                $id,
                $validated['quantity'],
                $userId,
                $sessionId
            );

            if ($validated['quantity'] <= 0) {
                return response()->json([
                    'message' => 'Article supprimé du panier',
                ]);
            }

            return response()->json([
                'message' => 'Quantité mise à jour',
                'item' => $item->load(['product.images', 'size']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove item from cart.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        [$userId, $sessionId] = $this->getIdentifiers($request);

        try {
            $this->cartService->removeItem($id, $userId, $sessionId);

            return response()->json([
                'message' => 'Article supprimé du panier',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Clear entire cart.
     */
    public function clear(Request $request): JsonResponse
    {
        [$userId, $sessionId] = $this->getIdentifiers($request);

        $this->cartService->clear($userId, $sessionId);

        return response()->json([
            'message' => 'Panier vidé',
        ]);
    }

    /**
     * Get user ID and session ID from request.
     * When an authenticated user has a session_id, merge any guest cart items first.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function getIdentifiers(Request $request): array
    {
        $userId = $request->user()?->id;
        $sessionId = $request->header('X-Session-Id');

        if ($userId && $sessionId) {
            $this->cartService->mergeSessionCart($sessionId, $userId);
        }

        return [$userId, $sessionId];
    }
}
