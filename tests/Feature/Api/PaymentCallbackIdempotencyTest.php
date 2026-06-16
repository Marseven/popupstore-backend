<?php

namespace Tests\Feature\Api;

use App\Events\PaymentReceived;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use App\Services\EbillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Gap 2 / §0.1 — the Ebilling webhook has no auth and can be replayed.
 * Processing it twice must NOT credit twice (one PaymentReceived, one admin notice).
 */
class PaymentCallbackIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(): PaymentTransaction
    {
        Role::factory()->customer()->create();
        $order = Order::factory()->create(['payment_status' => 'pending', 'status' => 'pending']);

        return PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'transaction_id' => 'BILL-IDEMP-123',
            'status' => 'pending',
        ]);
    }

    private function payload(string $billId): array
    {
        return [
            'billingid' => $billId,
            'reference' => 'REF-1',
            'paymentsystem' => 'airtelmoney',
            'amount' => 250,
        ];
    }

    public function test_duplicate_webhook_dispatches_payment_received_once(): void
    {
        Event::fake([PaymentReceived::class]);
        $transaction = $this->makeTransaction();
        $service = app(EbillingService::class);

        $service->handleCallback($this->payload($transaction->transaction_id));
        $service->handleCallback($this->payload($transaction->transaction_id));

        Event::assertDispatchedTimes(PaymentReceived::class, 1);
    }

    public function test_payment_received_notifies_each_admin_once_per_event(): void
    {
        // Webhook idempotency (one PaymentReceived per replay) is covered above; here
        // we lock the downstream contract: one event yields exactly one admin notice.
        // Invoked directly because the queued listener is afterCommit and RefreshDatabase
        // holds an uncommitted transaction, so it would never fire end-to-end in tests.
        Notification::fake();
        Role::factory()->superAdmin()->create();
        $admin = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->first()->id,
            'is_active' => true,
        ]);

        $transaction = $this->makeTransaction()->fresh();
        $event = new PaymentReceived($transaction->order, $transaction);

        app(\App\Listeners\SendPaymentReceivedNotification::class)->handle($event);

        Notification::assertSentToTimes($admin, PaymentReceivedNotification::class, 1);
    }

    public function test_mark_order_as_paid_clears_cart_synchronously(): void
    {
        // Invariant #3 — cart is emptied on payment success, inline (not queued).
        Role::factory()->customer()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $order = Order::factory()->guest()->create(['payment_status' => 'pending', 'status' => 'pending']);

        CartItem::factory()->create([
            'user_id' => null,
            'session_id' => $order->session_id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'transaction_id' => 'BILL-CART-1',
            'status' => 'pending',
        ]);

        app(EbillingService::class)->handleCallback($this->payload($transaction->transaction_id));

        $this->assertDatabaseCount('cart_items', 0);
    }
}
