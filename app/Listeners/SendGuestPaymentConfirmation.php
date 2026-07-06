<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Notifications\GuestPaymentConfirmation;
use App\Services\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

// Synchronous (no ShouldQueue) so the confirmation is sent immediately,
// but only after the payment is committed.
class SendGuestPaymentConfirmation implements ShouldHandleEventsAfterCommit
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(PaymentReceived $event): void
    {
        $this->notificationService->notifyOrderContact(
            $event->order,
            new GuestPaymentConfirmation($event->order, $event->transaction)
        );
    }
}
