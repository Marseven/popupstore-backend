<?php

namespace Tests\Unit\Notifications;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Notifications\GuestOrderConfirmation;
use App\Notifications\GuestPaymentConfirmation;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class GuestConfirmationContentTest extends TestCase
{
    public function test_order_mail_contains_order_number_and_track_link(): void
    {
        $order = Order::factory()->guest()->make([
            'order_number' => 'POP-20260616-9999',
            'guest_phone' => '+24177000001',
        ]);

        $mail = (new GuestOrderConfirmation($order))->toMail(new AnonymousNotifiable);
        $rendered = implode(' ', array_merge($mail->introLines, $mail->outroLines, [$mail->subject]));

        $this->assertStringContainsString('POP-20260616-9999', $rendered);
        $this->assertStringContainsString('/track', $mail->actionUrl ?? $rendered);
    }

    public function test_order_sms_contains_order_number(): void
    {
        $order = Order::factory()->guest()->make(['order_number' => 'POP-20260616-8888']);

        $sms = (new GuestOrderConfirmation($order))->toSms(new AnonymousNotifiable);

        $this->assertStringContainsString('POP-20260616-8888', $sms);
    }

    public function test_payment_sms_mentions_payment(): void
    {
        $order = Order::factory()->guest()->make(['order_number' => 'POP-20260616-7777']);
        $tx = PaymentTransaction::factory()->make(['order_id' => 0]);

        $sms = (new GuestPaymentConfirmation($order, $tx))->toSms(new AnonymousNotifiable);

        $this->assertStringContainsString('POP-20260616-7777', $sms);
    }
}
