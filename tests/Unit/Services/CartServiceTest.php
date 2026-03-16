<?php

namespace Tests\Unit\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Role;
use App\Models\Size;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the customer role so UserFactory can resolve role_id
        Role::factory()->customer()->create();

        $this->cartService = new CartService();
    }

    public function test_get_items_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        CartItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $items = $this->cartService->getItems($user->id, null);

        $this->assertCount(1, $items);
        $this->assertEquals(2, $items->first()->quantity);
    }

    public function test_get_items_for_guest_session(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $sessionId = 'test-session-123';

        CartItem::factory()->create([
            'user_id' => null,
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $items = $this->cartService->getItems(null, $sessionId);

        $this->assertCount(1, $items);
    }

    public function test_get_items_returns_empty_collection_when_no_items(): void
    {
        $user = User::factory()->create();

        $items = $this->cartService->getItems($user->id, null);

        $this->assertCount(0, $items);
    }

    public function test_add_item_creates_new_cart_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $size = Size::factory()->create();

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 10,
        ]);

        $item = $this->cartService->addItem($user->id, null, $product->id, $size->id, 2);

        $this->assertInstanceOf(CartItem::class, $item);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals($user->id, $item->user_id);
        $this->assertEquals($product->id, $item->product_id);
        $this->assertEquals($size->id, $item->size_id);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_add_item_creates_item_without_size(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $item = $this->cartService->addItem($user->id, null, $product->id, null, 1);

        $this->assertInstanceOf(CartItem::class, $item);
        $this->assertNull($item->size_id);
        $this->assertEquals(1, $item->quantity);
    }

    public function test_add_item_increments_existing_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $size = Size::factory()->create();

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 10,
        ]);

        $this->cartService->addItem($user->id, null, $product->id, $size->id, 2);
        $item = $this->cartService->addItem($user->id, null, $product->id, $size->id, 3);

        $this->assertEquals(5, $item->quantity);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_add_item_for_guest_session(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $sessionId = 'guest-session-456';

        $item = $this->cartService->addItem(null, $sessionId, $product->id, null, 1);

        $this->assertInstanceOf(CartItem::class, $item);
        $this->assertNull($item->user_id);
        $this->assertEquals($sessionId, $item->session_id);
    }

    public function test_add_item_throws_when_product_inactive(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ce produit n\'est plus disponible');

        $this->cartService->addItem($user->id, null, $product->id, null, 1);
    }

    public function test_add_item_throws_when_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $size = Size::factory()->create();

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stock insuffisant pour cette taille');

        $this->cartService->addItem($user->id, null, $product->id, $size->id, 5);
    }

    public function test_add_item_throws_when_no_stock_record(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $size = Size::factory()->create();

        // No ProductStock created for this product/size combination
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stock insuffisant pour cette taille');

        $this->cartService->addItem($user->id, null, $product->id, $size->id, 1);
    }

    public function test_update_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $item = CartItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'size_id' => null,
            'quantity' => 2,
        ]);

        $updated = $this->cartService->updateQuantity($item->id, 5, $user->id, null);

        $this->assertEquals(5, $updated->quantity);
        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 5,
        ]);
    }

    public function test_update_quantity_to_zero_deletes_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $item = CartItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'size_id' => null,
            'quantity' => 2,
        ]);

        $this->cartService->updateQuantity($item->id, 0, $user->id, null);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_update_quantity_to_negative_deletes_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $item = CartItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'size_id' => null,
            'quantity' => 2,
        ]);

        $this->cartService->updateQuantity($item->id, -1, $user->id, null);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_update_quantity_checks_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $size = Size::factory()->create();

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 3,
        ]);

        $item = CartItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stock insuffisant');

        $this->cartService->updateQuantity($item->id, 10, $user->id, null);
    }

    public function test_remove_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $item = CartItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->cartService->removeItem($item->id, $user->id, null);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_remove_item_for_guest_session(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $sessionId = 'guest-remove-test';

        $item = CartItem::factory()->create([
            'user_id' => null,
            'session_id' => $sessionId,
            'product_id' => $product->id,
        ]);

        $this->cartService->removeItem($item->id, null, $sessionId);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_clear_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        CartItem::factory()->count(3)->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->cartService->clear($user->id, null);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_clear_cart_only_clears_users_items(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        CartItem::factory()->count(2)->create([
            'user_id' => $user1->id,
            'product_id' => $product->id,
        ]);
        CartItem::factory()->create([
            'user_id' => $user2->id,
            'product_id' => $product->id,
        ]);

        $this->cartService->clear($user1->id, null);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['user_id' => $user2->id]);
    }

    public function test_clear_guest_session_cart(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $sessionId = 'session-to-clear';

        CartItem::factory()->count(2)->create([
            'user_id' => null,
            'session_id' => $sessionId,
            'product_id' => $product->id,
        ]);

        $this->cartService->clear(null, $sessionId);

        $this->assertDatabaseMissing('cart_items', ['session_id' => $sessionId]);
    }

    public function test_merge_session_cart_transfers_items_to_user(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $sessionId = 'test-session';

        CartItem::factory()->create([
            'user_id' => null,
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->cartService->mergeSessionCart($sessionId, $user->id);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'session_id' => $sessionId,
            'user_id' => null,
        ]);
    }

    public function test_merge_session_cart_combines_duplicate_items(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $size = Size::factory()->create();
        $sessionId = 'merge-session';

        // Existing item in user's cart
        CartItem::factory()->create([
            'user_id' => $user->id,
            'session_id' => null,
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 2,
        ]);

        // Same product/size in session cart
        CartItem::factory()->create([
            'user_id' => null,
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 3,
        ]);

        $this->cartService->mergeSessionCart($sessionId, $user->id);

        // Should combine quantities: 2 + 3 = 5
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 5,
        ]);
        // Session item should be deleted (not transferred)
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_merge_session_cart_with_no_session_items(): void
    {
        $user = User::factory()->create();
        $sessionId = 'empty-session';

        // Should not throw, just do nothing
        $this->cartService->mergeSessionCart($sessionId, $user->id);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_get_total(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'price' => 5000,
        ]);

        CartItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'size_id' => null,
            'quantity' => 3,
        ]);

        $total = $this->cartService->getTotal($user->id, null);

        $this->assertEquals(15000, $total['subtotal']);
        $this->assertEquals(3, $total['item_count']);
        $this->assertArrayHasKey('items', $total);
    }

    public function test_get_total_with_multiple_items(): void
    {
        $user = User::factory()->create();
        $product1 = Product::factory()->create([
            'is_active' => true,
            'price' => 5000,
        ]);
        $product2 = Product::factory()->create([
            'is_active' => true,
            'price' => 3000,
        ]);

        CartItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product1->id,
            'size_id' => null,
            'quantity' => 2,
        ]);
        CartItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product2->id,
            'size_id' => null,
            'quantity' => 1,
        ]);

        $total = $this->cartService->getTotal($user->id, null);

        // (5000 * 2) + (3000 * 1) = 13000
        $this->assertEquals(13000, $total['subtotal']);
        $this->assertEquals(3, $total['item_count']);
    }

    public function test_get_total_with_empty_cart(): void
    {
        $user = User::factory()->create();

        $total = $this->cartService->getTotal($user->id, null);

        $this->assertEquals(0, $total['subtotal']);
        $this->assertEquals(0, $total['item_count']);
    }
}
