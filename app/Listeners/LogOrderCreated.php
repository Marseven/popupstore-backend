<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use Illuminate\Support\Facades\Log;

class LogOrderCreated
{
    public function handle(OrderCreated $event): void
    {
        Log::info('Order created', [
            'order_number' => $event->order->order_number,
            'total' => $event->order->total,
            'user_id' => $event->order->user_id,
            'is_guest' => $event->order->user_id === null,
        ]);
    }
}
