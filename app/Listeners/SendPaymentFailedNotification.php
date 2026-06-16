<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Notifications\PaymentFailedNotification;
use App\Services\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentFailedNotification implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private NotificationService $notificationService) {}

    public function handle(PaymentFailed $event): void
    {
        $this->notificationService->notifyAdmins(
            new PaymentFailedNotification($event->order, $event->transaction, $event->reason)
        );
    }
}
