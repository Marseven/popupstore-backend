<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Collection;

class CartService
{
    public function getItems(?int $userId, ?string $sessionId): Collection
    {
        return CartItem::with(['product.images', 'product.mediaContent', 'size'])
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when(!$userId && $sessionId, fn($q) => $q->where('session_id', $sessionId))
            ->get();
    }

    public function addItem(?int $userId, ?string $sessionId, int $productId, ?int $sizeId, int $quantity = 1): CartItem
    {
        $product = Product::findOrFail($productId);

        if (!$product->is_active) {
            throw new \Exception('Ce produit n\'est plus disponible');
        }

        // Check stock
        if ($sizeId) {
            $stock = ProductStock::where('product_id', $productId)
                ->where('size_id', $sizeId)
                ->first();

            if (!$stock || $stock->quantity < $quantity) {
                throw new \Exception('Stock insuffisant pour cette taille');
            }
        }

        // Check if item already in cart
        $existingItem = CartItem::where('product_id', $productId)
            ->where('size_id', $sizeId)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when(!$userId && $sessionId, fn($q) => $q->where('session_id', $sessionId))
            ->first();

        if ($existingItem) {
            $existingItem->update(['quantity' => $existingItem->quantity + $quantity]);
            return $existingItem->fresh();
        }

        return CartItem::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'product_id' => $productId,
            'size_id' => $sizeId,
            'quantity' => $quantity,
        ]);
    }

    public function updateQuantity(int $cartItemId, int $quantity, ?int $userId, ?string $sessionId): CartItem
    {
        $item = $this->findCartItem($cartItemId, $userId, $sessionId);

        if ($quantity <= 0) {
            $item->delete();
            return $item;
        }

        // Check stock
        if ($item->size_id) {
            $stock = ProductStock::where('product_id', $item->product_id)
                ->where('size_id', $item->size_id)
                ->first();

            if (!$stock || $stock->quantity < $quantity) {
                throw new \Exception('Stock insuffisant');
            }
        }

        $item->update(['quantity' => $quantity]);
        return $item->fresh();
    }

    public function removeItem(int $cartItemId, ?int $userId, ?string $sessionId): void
    {
        $item = $this->findCartItem($cartItemId, $userId, $sessionId);
        $item->delete();
    }

    public function clear(?int $userId, ?string $sessionId): void
    {
        CartItem::when($userId, fn($q) => $q->where('user_id', $userId))
            ->when(!$userId && $sessionId, fn($q) => $q->where('session_id', $sessionId))
            ->delete();
    }

    public function mergeSessionCart(string $sessionId, int $userId): void
    {
        $sessionItems = CartItem::where('session_id', $sessionId)->get();

        foreach ($sessionItems as $sessionItem) {
            $existingItem = CartItem::where('user_id', $userId)
                ->where('product_id', $sessionItem->product_id)
                ->where('size_id', $sessionItem->size_id)
                ->first();

            if ($existingItem) {
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $sessionItem->quantity,
                ]);
                $sessionItem->delete();
            } else {
                $sessionItem->update([
                    'user_id' => $userId,
                    'session_id' => null,
                ]);
            }
        }
    }

    public function getTotal(?int $userId, ?string $sessionId): array
    {
        $items = $this->getItems($userId, $sessionId);

        $subtotal = $items->sum(fn($item) => $item->product->price * $item->quantity);
        $itemCount = $items->sum('quantity');

        return [
            'subtotal' => round($subtotal, 2),
            'item_count' => $itemCount,
            'items' => $items,
        ];
    }

    private function findCartItem(int $cartItemId, ?int $userId, ?string $sessionId): CartItem
    {
        return CartItem::where('id', $cartItemId)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when(!$userId && $sessionId, fn($q) => $q->where('session_id', $sessionId))
            ->firstOrFail();
    }
}
