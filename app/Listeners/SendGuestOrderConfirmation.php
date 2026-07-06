<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Notifications\GuestOrderConfirmation;
use App\Services\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

// Synchronous (no ShouldQueue) so the email/SMS is sent during the request,
// but only after the order transaction commits.
class SendGuestOrderConfirmation implements ShouldHandleEventsAfterCommit
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(OrderCreated $event): void
    {
        $this->notificationService->notifyOrderContact(
            $event->order,
            new GuestOrderConfirmation($event->order)
        );
    }
}
