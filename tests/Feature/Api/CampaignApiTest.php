<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use App\Models\CampaignTeam;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
    }

    public function test_public_can_view_campaign_and_leaderboard(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignTeam::factory()->create(['campaign_id' => $campaign->id, 'points_total' => 30]);
        CampaignTeam::factory()->create(['campaign_id' => $campaign->id, 'points_total' => 80]);

        $this->getJson("/api/campaigns/{$campaign->slug}")->assertStatus(200)
            ->assertJsonStructure(['campaign', 'teams']);

        $board = $this->getJson("/api/campaigns/{$campaign->slug}/leaderboard")->assertStatus(200)
            ->json('leaderboard');

        $this->assertSame(80, $board[0]['points_total']); // highest first
    }

    public function test_guest_can_read_entitlements_for_their_session_order(): void
    {
        $campaign = Campaign::factory()->create();
        $team = CampaignTeam::factory()->create(['campaign_id' => $campaign->id]);
        $order = Order::factory()->guest()->create(['session_id' => 'sess-camp-1']);
        CampaignEntitlement::create([
            'order_id' => $order->id, 'team_id' => $team->id, 'type' => 'catalog', 'code' => 'CODE123XYZ',
        ]);

        $this->getJson("/api/orders/{$order->order_number}/entitlements", ['X-Session-Id' => 'sess-camp-1'])
            ->assertStatus(200)
            ->assertJsonFragment(['code' => 'CODE123XYZ']);
    }

    public function test_entitlements_not_exposed_to_other_session(): void
    {
        $order = Order::factory()->guest()->create(['session_id' => 'sess-owner']);

        $this->getJson("/api/orders/{$order->order_number}/entitlements", ['X-Session-Id' => 'sess-attacker'])
            ->assertStatus(404);
    }

    public function test_admin_can_create_campaign_and_team(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::factory()->create(['slug' => 'super_admin', 'name' => 'SA'])->id,
        ]);

        $campaignId = $this->actingAs($admin)
            ->postJson('/api/admin/campaigns', ['name' => 'Battle 241'])
            ->assertStatus(201)
            ->json('campaign.id');

        $this->actingAs($admin)
            ->postJson("/api/admin/campaigns/{$campaignId}/teams", [
                'name' => 'Team Alpha', 'team_code' => 'ALPHA241',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('campaign_teams', ['team_code' => 'ALPHA241']);
    }

    public function test_customer_cannot_manage_campaigns(): void
    {
        $customer = User::factory()->create(['role_id' => Role::where('slug', 'customer')->first()->id]);

        $this->actingAs($customer)
            ->postJson('/api/admin/campaigns', ['name' => 'X'])
            ->assertStatus(403);
    }
}
