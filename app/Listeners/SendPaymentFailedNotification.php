<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Notifications\PaymentFailedNotification;
use App\Services\NotificationService;

class SendPaymentFailedNotification
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(PaymentFailed $event): void
    {
        $this->notificationService->notifyAdmins(
            new PaymentFailedNotification($event->order, $event->transaction, $event->reason)
        );
    }
}
