<?php

namespace App\Providers;

use App\Events\OrderCreated;
use App\Events\PaymentFailed;
use App\Events\PaymentReceived;
use App\Listeners\LogOrderCreated;
use App\Listeners\LogPaymentFailed;
use App\Listeners\LogPaymentReceived;
use App\Listeners\SendOrderNotification;
use App\Listeners\SendPaymentReceivedNotification;
use App\Listeners\SendPaymentFailedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderCreated::class => [
            LogOrderCreated::class,
            SendOrderNotification::class,
        ],
        PaymentReceived::class => [
            LogPaymentReceived::class,
            SendPaymentReceivedNotification::class,
        ],
        PaymentFailed::class => [
            LogPaymentFailed::class,
            SendPaymentFailedNotification::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
