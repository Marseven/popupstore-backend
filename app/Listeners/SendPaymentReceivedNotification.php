<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Notifications\PaymentReceivedNotification;
use App\Services\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentReceivedNotification implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private NotificationService $notificationService) {}

    public function handle(PaymentReceived $event): void
    {
        $this->notificationService->notifyAdmins(
            new PaymentReceivedNotification($event->order, $event->transaction)
        );
    }
}
