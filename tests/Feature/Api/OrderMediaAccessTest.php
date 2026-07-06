<?php

namespace Tests\Feature\Api;

use App\Models\MediaContent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMediaAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
    }

    private function guestOrderWithMedia(): Order
    {
        $product = Product::factory()->create(['is_active' => true]);
        $audio = MediaContent::factory()->create(['type' => 'audio', 'title' => 'EDDG Track']);
        $video = MediaContent::factory()->create(['type' => 'video', 'title' => 'EDDG Video']);
        $product->mediaContents()->sync([$audio->id, $video->id]);

        $order = Order::factory()->guest()->create([
            'guest_phone' => '+24177000001',
            'guest_email' => 'buyer@example.com',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
        ]);

        return $order;
    }

    public function test_track_by_phone_returns_purchased_media(): void
    {
        $order = $this->guestOrderWithMedia();

        $this->getJson('/api/orders/track?order_number='.$order->order_number.'&phone=%2B24177000001')
            ->assertOk()
            ->assertJsonCount(2, 'order.items.0.product.media_contents')
            ->assertJsonPath('order.items.0.product.media_contents.0.title', 'EDDG Track');
    }

    public function test_track_by_email_returns_order(): void
    {
        $order = $this->guestOrderWithMedia();

        $this->getJson('/api/orders/track?order_number='.$order->order_number.'&contact=buyer@example.com')
            ->assertOk()
            ->assertJsonPath('order.order_number', $order->order_number)
            ->assertJsonCount(2, 'order.items.0.product.media_contents');
    }

    public function test_track_with_wrong_contact_returns_404(): void
    {
        $order = $this->guestOrderWithMedia();

        $this->getJson('/api/orders/track?order_number='.$order->order_number.'&contact=someone-else@example.com')
            ->assertStatus(404);
    }
}
