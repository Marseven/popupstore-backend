<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Notifications\GuestPaymentConfirmation;
use App\Services\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendGuestPaymentConfirmation implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private NotificationService $notificationService) {}

    public function handle(PaymentReceived $event): void
    {
        $this->notificationService->notifyOrderContact(
            $event->order,
            new GuestPaymentConfirmation($event->order, $event->transaction)
        );
    }
}
