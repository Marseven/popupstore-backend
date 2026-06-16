<?php

namespace Tests\Feature\Notifications;

use App\Events\OrderCreated;
use App\Events\PaymentReceived;
use App\Listeners\SendGuestOrderConfirmation;
use App\Listeners\SendGuestPaymentConfirmation;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\GuestOrderConfirmation;
use App\Notifications\GuestPaymentConfirmation;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Gap 1 — the guest only sees their order number on screen; confirm it out-of-band.
 * Listeners are invoked directly because they are afterCommit (would not fire under
 * RefreshDatabase's open transaction).
 */
class GuestConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
    }

    public function test_order_confirmation_sent_to_guest_contact(): void
    {
        Notification::fake();
        $order = Order::factory()->guest()->create([
            'guest_phone' => '+24177000001',
            'guest_email' => 'buyer@example.com',
        ]);

        app(SendGuestOrderConfirmation::class)->handle(new OrderCreated($order));

        Notification::assertSentOnDemand(GuestOrderConfirmation::class);
    }

    public function test_payment_confirmation_sent_to_guest_contact(): void
    {
        Notification::fake();
        $order = Order::factory()->guest()->create([
            'guest_phone' => '+24177000002',
            'guest_email' => null,
        ]);
        $tx = PaymentTransaction::factory()->create(['order_id' => $order->id, 'status' => 'success']);

        app(SendGuestPaymentConfirmation::class)->handle(new PaymentReceived($order->fresh(), $tx));

        Notification::assertSentOnDemand(GuestPaymentConfirmation::class);
    }

    public function test_no_confirmation_when_order_has_no_contact(): void
    {
        Notification::fake();
        // Defensive: an order with neither phone nor email must not attempt a send.
        $order = Order::factory()->guest()->create([
            'guest_phone' => null,
            'guest_email' => null,
            'shipping_phone' => '',
        ]);

        app(SendGuestOrderConfirmation::class)->handle(new OrderCreated($order));

        Notification::assertNothingSent();
    }

    public function test_uses_sms_only_when_email_absent(): void
    {
        // via() must drop the mail channel when the guest gave no email.
        $order = Order::factory()->guest()->make(['guest_email' => null, 'guest_phone' => '+24177000003']);
        $notifiable = Notification::route('sms', '+24177000003');

        $channels = (new GuestOrderConfirmation($order))->via($notifiable);

        $this->assertContains(SmsChannel::class, $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_listeners_are_queued_after_commit(): void
    {
        foreach ([SendGuestOrderConfirmation::class, SendGuestPaymentConfirmation::class] as $listener) {
            $this->assertInstanceOf(ShouldQueue::class, app($listener));
            $this->assertInstanceOf(ShouldHandleEventsAfterCommit::class, app($listener));
        }
    }
}
