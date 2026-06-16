<?php

namespace Tests\Unit\Async;

use App\Listeners\SendOrderNotification;
use App\Listeners\SendPaymentFailedNotification;
use App\Listeners\SendPaymentReceivedNotification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Gap 2 — listeners that perform heavy side effects (admin notifications, web push)
 * must run on the database queue, after the DB transaction commits.
 */
class QueuedListenersTest extends TestCase
{
    use RefreshDatabase;

    public static function notificationListeners(): array
    {
        return [
            [SendOrderNotification::class],
            [SendPaymentReceivedNotification::class],
            [SendPaymentFailedNotification::class],
        ];
    }

    /**
     * @dataProvider notificationListeners
     */
    public function test_notification_listener_is_queued(string $listener): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            app($listener),
            "{$listener} must implement ShouldQueue so it runs off the request cycle."
        );
    }

    /**
     * @dataProvider notificationListeners
     */
    public function test_notification_listener_dispatches_after_commit(string $listener): void
    {
        $this->assertInstanceOf(
            ShouldHandleEventsAfterCommit::class,
            app($listener),
            "{$listener} must run after commit so it never reads uncommitted order/payment rows."
        );
    }

    public function test_failed_jobs_table_exists(): void
    {
        // The database queue records failures in failed_jobs; without it queue:work
        // cannot persist a failed job and the failure is silently lost.
        $this->assertTrue(
            Schema::hasTable('failed_jobs'),
            'failed_jobs table migration is required for the database queue driver.'
        );
    }
}
