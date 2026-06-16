<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
    }

    // ---- API versioning (non-breaking) ----

    public function test_v1_prefix_serves_endpoints(): void
    {
        $this->getJson('/api/v1/health')->assertStatus(200);
    }

    public function test_legacy_api_prefix_still_works(): void
    {
        $this->getJson('/api/health')->assertStatus(200);
    }

    // ---- Idempotency-Key on payment initiation ----

    public function test_idempotent_initiate_replays_and_creates_one_transaction(): void
    {
        Http::fake(['*' => Http::response(['e_bill' => ['bill_id' => 'BILL-IDEM-1']], 200)]);
        $order = Order::factory()->guest()->create([
            'payment_status' => 'pending',
            'status' => 'pending',
            'total' => 250,
            'guest_phone' => '+24177000001',
        ]);

        $payload = ['order_number' => $order->order_number, 'phone' => '+24177000001'];
        $headers = ['Idempotency-Key' => 'key-abc-123'];

        $first = $this->postJson('/api/payments/initiate', $payload, $headers);
        $second = $this->postJson('/api/payments/initiate', $payload, $headers);

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertSame($first->getContent(), $second->getContent());
        // The replayed call must NOT create a second bill/transaction.
        $this->assertSame(1, PaymentTransaction::where('order_id', $order->id)->count());
    }

    // ---- Webhook authentication ----

    public function test_webhook_rejected_with_wrong_secret_when_configured(): void
    {
        config(['ebilling.callback_secret' => 'top-secret']);

        $this->postJson('/api/payments/callback', ['billingid' => 'x'], ['X-Callback-Token' => 'wrong'])
            ->assertStatus(403);
    }

    public function test_webhook_accepted_with_correct_secret(): void
    {
        config(['ebilling.callback_secret' => 'top-secret']);

        $this->postJson('/api/payments/callback', ['billingid' => 'unknown'], ['X-Callback-Token' => 'top-secret'])
            ->assertStatus(200);
    }

    public function test_webhook_allowed_when_no_secret_configured(): void
    {
        config(['ebilling.callback_secret' => null]);

        $this->postJson('/api/payments/callback', ['billingid' => 'unknown'])
            ->assertStatus(200);
    }
}
