<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Notifications\PaymentReceivedNotification;
use App\Services\NotificationService;

class SendPaymentReceivedNotification
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(PaymentReceived $event): void
    {
        $this->notificationService->notifyAdmins(
            new PaymentReceivedNotification($event->order, $event->transaction)
        );
    }
}
