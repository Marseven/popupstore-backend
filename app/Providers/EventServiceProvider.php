<?php

namespace App\Providers;

use App\Events\OrderCreated;
use App\Events\PaymentFailed;
use App\Events\PaymentReceived;
use App\Listeners\LogOrderCreated;
use App\Listeners\LogPaymentFailed;
use App\Listeners\LogPaymentReceived;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderCreated::class => [
            LogOrderCreated::class,
        ],
        PaymentReceived::class => [
            LogPaymentReceived::class,
        ],
        PaymentFailed::class => [
            LogPaymentFailed::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
