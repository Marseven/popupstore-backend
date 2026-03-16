<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use Illuminate\Support\Facades\Log;

class LogPaymentReceived
{
    public function handle(PaymentReceived $event): void
    {
        Log::info('Payment received', [
            'order_number' => $event->order->order_number,
            'transaction_id' => $event->transaction->transaction_id,
            'amount' => $event->transaction->amount,
            'provider' => $event->transaction->provider,
        ]);
    }
}
