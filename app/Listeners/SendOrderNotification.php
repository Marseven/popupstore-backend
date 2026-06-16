<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Notifications\NewOrderNotification;
use App\Services\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderNotification implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private NotificationService $notificationService) {}

    public function handle(OrderCreated $event): void
    {
        $this->notificationService->notifyAdmins(
            new NewOrderNotification($event->order)
        );
    }
}
