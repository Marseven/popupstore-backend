<?php

namespace App\Events;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public PaymentTransaction $transaction
    ) {}
}
