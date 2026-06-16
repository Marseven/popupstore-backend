<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Services\RevenueSplitService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordRevenueShares implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private RevenueSplitService $revenueSplit) {}

    public function handle(PaymentReceived $event): void
    {
        // Idempotent in the service (one set of entries per order_id), so a webhook
        // replay or job retry never double-records.
        $this->revenueSplit->recordForOrder($event->order);
    }
}
