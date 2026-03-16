<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use Illuminate\Support\Facades\Log;

class LogPaymentFailed
{
    public function handle(PaymentFailed $event): void
    {
        Log::warning('Payment failed', [
            'order_number' => $event->order->order_number,
            'transaction_id' => $event->transaction->transaction_id,
            'reason' => $event->reason,
            'provider' => $event->transaction->provider,
        ]);
    }
}
