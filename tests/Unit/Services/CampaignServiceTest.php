<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use App\Models\CampaignPoint;
use App\Models\CampaignTeam;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignServiceTest extends TestCase
{
    use RefreshDatabase;

    private CampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
        $this->service = new CampaignService;
    }

    /** Order with one item (qty) attributed to a team in $campaign. */
    private function orderForTeam(Campaign $campaign, int $quantity = 3, ?\DateTimeInterface $orderedAt = null): array
    {
        $team = CampaignTeam::factory()->create(['campaign_id' => $campaign->id]);
        $product = Product::factory()->create(['campaign_team_id' => $team->id]);
        $order = Order::factory()->create(['created_at' => $orderedAt ?? now()]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'total' => 1000,
        ]);

        return [$order->fresh(), $team];
    }

    public function test_awards_points_per_item_quantity(): void
    {
        $campaign = Campaign::factory()->create(['settings' => ['points_rule' => 'per_item']]);
        [$order, $team] = $this->orderForTeam($campaign, quantity: 3);

        $this->service->awardPoints($order);

        $this->assertSame(3, $team->fresh()->points_total);
        $this->assertSame(1, CampaignPoint::where('order_id', $order->id)->count());
    }

    public function test_award_is_idempotent_per_order(): void
    {
        $campaign = Campaign::factory()->create();
        [$order, $team] = $this->orderForTeam($campaign, quantity: 2);

        $this->service->awardPoints($order);
        $this->service->awardPoints($order->fresh());

        $this->assertSame(2, $team->fresh()->points_total);
        $this->assertSame(1, CampaignPoint::where('order_id', $order->id)->count());
    }

    public function test_no_points_when_campaign_not_active(): void
    {
        $campaign = Campaign::factory()->draft()->create();
        [$order, $team] = $this->orderForTeam($campaign, quantity: 5);

        $this->service->awardPoints($order);

        $this->assertSame(0, $team->fresh()->points_total);
        $this->assertSame(0, CampaignPoint::where('order_id', $order->id)->count());
    }

    public function test_no_points_outside_campaign_window(): void
    {
        // Campaign window is in the past; the order is dated now → no points.
        $campaign = Campaign::factory()->past()->create();
        [$order, $team] = $this->orderForTeam($campaign, quantity: 5, orderedAt: now());

        $this->service->awardPoints($order);

        $this->assertSame(0, $team->fresh()->points_total);
    }

    public function test_per_amount_rule_awards_item_total(): void
    {
        $campaign = Campaign::factory()->create(['settings' => ['points_rule' => 'per_amount']]);
        [$order, $team] = $this->orderForTeam($campaign, quantity: 1);

        $this->service->awardPoints($order);

        $this->assertSame(1000, $team->fresh()->points_total); // item total
    }

    public function test_leaderboard_is_sorted_by_points(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignTeam::factory()->create(['campaign_id' => $campaign->id, 'name' => 'Low', 'points_total' => 10]);
        CampaignTeam::factory()->create(['campaign_id' => $campaign->id, 'name' => 'High', 'points_total' => 99]);
        CampaignTeam::factory()->create(['campaign_id' => $campaign->id, 'name' => 'Mid', 'points_total' => 50]);

        $board = $this->service->leaderboard($campaign);

        $this->assertSame(['High', 'Mid', 'Low'], $board->pluck('name')->all());
    }

    public function test_issues_unique_entitlement_per_team(): void
    {
        $campaign = Campaign::factory()->create();
        [$order] = $this->orderForTeam($campaign, quantity: 2);

        $this->service->issueEntitlements($order);
        $this->service->issueEntitlements($order->fresh()); // idempotent

        $entitlements = CampaignEntitlement::where('order_id', $order->id)->get();
        $this->assertCount(1, $entitlements);
        $this->assertNotEmpty($entitlements->first()->code);
    }
}
