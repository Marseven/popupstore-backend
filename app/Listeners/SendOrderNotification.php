<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Notifications\NewOrderNotification;
use App\Services\NotificationService;

class SendOrderNotification
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(OrderCreated $event): void
    {
        $this->notificationService->notifyAdmins(
            new NewOrderNotification($event->order)
        );
    }
}
