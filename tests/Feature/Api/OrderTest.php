<?php

namespace Tests\Feature\Api;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Role;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
        $this->user = User::factory()->create();
    }

    // ---------------------------------------------------------------
    // Store (create order from cart)
    // ---------------------------------------------------------------

    public function test_store_creates_order_from_cart(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 10000]);
        $size = Size::factory()->create(['name' => 'M']);

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 10,
        ]);

        CartItem::factory()->create([
            'user_id' => $this->user->id,
            'session_id' => null,
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'shipping_name' => 'John Doe',
                'shipping_phone' => '+24177000001',
                'shipping_address' => '123 Main St',
                'shipping_city' => 'Libreville',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'order' => ['id', 'order_number', 'status', 'items']])
            ->assertJsonPath('order.status', 'pending');

        $this->assertDatabaseCount('orders', 1);
        // Cart should be cleared after order creation
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_store_decrements_stock_on_order(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 5000]);
        $size = Size::factory()->create(['name' => 'L']);

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 10,
        ]);

        CartItem::factory()->create([
            'user_id' => $this->user->id,
            'session_id' => null,
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 3,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'shipping_name' => 'John Doe',
                'shipping_phone' => '+24177000001',
                'shipping_address' => '123 Main St',
                'shipping_city' => 'Libreville',
            ]);

        $this->assertDatabaseHas('product_stock', [
            'product_id' => $product->id,
            'size_id' => $size->id,
            'quantity' => 7,
        ]);
    }

    public function test_store_fails_with_empty_cart(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'shipping_name' => 'John Doe',
                'shipping_phone' => '+24177000001',
                'shipping_address' => '123 Main St',
                'shipping_city' => 'Libreville',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Votre panier est vide');
    }

    public function test_store_requires_shipping_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'shipping_name',
                'shipping_phone',
                'shipping_address',
                'shipping_city',
            ]);
    }

    public function test_store_calculates_correct_total(): void
    {
        $product1 = Product::factory()->create(['is_active' => true, 'price' => 10000]);
        $product2 = Product::factory()->create(['is_active' => true, 'price' => 5000]);

        CartItem::factory()->create([
            'user_id' => $this->user->id,
            'session_id' => null,
            'product_id' => $product1->id,
            'size_id' => null,
            'quantity' => 2,
        ]);

        CartItem::factory()->create([
            'user_id' => $this->user->id,
            'session_id' => null,
            'product_id' => $product2->id,
            'size_id' => null,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'shipping_name' => 'John Doe',
                'shipping_phone' => '+24177000001',
                'shipping_address' => '123 Main St',
                'shipping_city' => 'Libreville',
            ]);

        $response->assertStatus(201);

        // 10000 * 2 + 5000 * 1 = 25000
        $order = Order::first();
        $this->assertEquals(25000.00, (float) $order->total);
    }

    public function test_guest_can_create_order_with_session_id(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 5000]);
        $sessionId = 'guest-session-123';

        CartItem::create([
            'user_id' => null,
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'size_id' => null,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/orders', [
            'shipping_name' => 'Jane Doe',
            'shipping_phone' => '+24177000002',
            'shipping_address' => '456 Side St',
            'shipping_city' => 'Libreville',
            'guest_phone' => '+24177000002',
        ], ['X-Session-Id' => $sessionId]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'order']);

        $this->assertDatabaseHas('orders', [
            'guest_phone' => '+24177000002',
            'user_id' => null,
        ]);
    }

    public function test_guest_order_requires_guest_phone(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 5000]);
        $sessionId = 'guest-session-456';

        CartItem::create([
            'user_id' => null,
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'size_id' => null,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/orders', [
            'shipping_name' => 'Jane Doe',
            'shipping_phone' => '+24177000002',
            'shipping_address' => '456 Side St',
            'shipping_city' => 'Libreville',
        ], ['X-Session-Id' => $sessionId]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('guest_phone');
    }

    public function test_store_generates_order_number(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 10000]);

        CartItem::factory()->create([
            'user_id' => $this->user->id,
            'session_id' => null,
            'product_id' => $product->id,
            'size_id' => null,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'shipping_name' => 'John Doe',
                'shipping_phone' => '+24177000001',
                'shipping_address' => '123 Main St',
                'shipping_city' => 'Libreville',
            ]);

        $response->assertStatus(201);

        $orderNumber = $response->json('order.order_number');
        $this->assertStringStartsWith('POP-', $orderNumber);
    }

    // ---------------------------------------------------------------
    // Index (list user orders)
    // ---------------------------------------------------------------

    public function test_index_returns_user_orders(): void
    {
        Order::factory()->count(3)->create(['user_id' => $this->user->id]);
        // Another user's orders should not appear
        Order::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/orders');

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);

        $this->assertEquals(3, $response->json('total'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/orders');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Show (order details by order_number)
    // ---------------------------------------------------------------

    public function test_show_returns_order_for_owner(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/orders/{$order->order_number}");

        $response->assertOk()
            ->assertJsonPath('order.order_number', $order->order_number);
    }

    public function test_show_returns_404_for_other_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/orders/{$order->order_number}");

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Track (guest order tracking)
    // ---------------------------------------------------------------

    public function test_track_finds_order_by_guest_phone_and_order_number(): void
    {
        $order = Order::factory()->guest()->create([
            'guest_phone' => '+24177000099',
        ]);

        $response = $this->getJson("/api/orders/track?phone=+24177000099&order_number={$order->order_number}");

        $response->assertOk()
            ->assertJsonPath('order.order_number', $order->order_number);
    }

    public function test_track_finds_order_by_shipping_phone_and_order_number(): void
    {
        $order = Order::factory()->create([
            'shipping_phone' => '+24177000088',
        ]);

        $response = $this->getJson("/api/orders/track?phone=+24177000088&order_number={$order->order_number}");

        $response->assertOk()
            ->assertJsonPath('order.order_number', $order->order_number);
    }

    public function test_track_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/orders/track?phone=+24177000000&order_number=POP-INVALID');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Aucune commande trouvée avec ces informations');
    }

    public function test_track_requires_phone_and_order_number(): void
    {
        $response = $this->getJson('/api/orders/track');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'order_number']);
    }
}
