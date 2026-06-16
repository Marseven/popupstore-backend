<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Services\CampaignService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardCampaignPoints implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private CampaignService $campaigns) {}

    public function handle(PaymentReceived $event): void
    {
        // Both steps are idempotent per order_id (safe on webhook replay / retry).
        $this->campaigns->awardPoints($event->order);
        $this->campaigns->issueEntitlements($event->order);
    }
}
