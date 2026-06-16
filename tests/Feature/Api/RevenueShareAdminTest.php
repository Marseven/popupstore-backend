<?php

namespace Tests\Feature\Api;

use App\Models\Collection;
use App\Models\PayoutEntry;
use App\Models\RevenueShare;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueShareAdminTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $slug): User
    {
        $role = Role::where('slug', $slug)->first()
            ?? Role::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
    }

    private function shareData(array $overrides = []): array
    {
        return array_merge([
            'beneficiary_label' => 'Artiste X',
            'payout_phone' => '+24107000000',
            'payout_provider' => 'airtelmoney',
            'percentage' => 70,
        ], $overrides);
    }

    public function test_super_admin_can_create_revenue_share(): void
    {
        $admin = $this->userWithRole('super_admin');
        $collection = Collection::factory()->partner()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/collections/{$collection->id}/revenue-shares", $this->shareData())
            ->assertStatus(201);

        $this->assertDatabaseHas('revenue_shares', [
            'collection_id' => $collection->id,
            'beneficiary_label' => 'Artiste X',
        ]);
    }

    public function test_manager_cannot_create_revenue_share(): void
    {
        $manager = $this->userWithRole('manager');
        $collection = Collection::factory()->partner()->create();

        $this->actingAs($manager)
            ->postJson("/api/admin/collections/{$collection->id}/revenue-shares", $this->shareData())
            ->assertStatus(403);
    }

    public function test_customer_cannot_access_payouts(): void
    {
        $customer = $this->userWithRole('customer');

        $this->actingAs($customer)
            ->getJson('/api/admin/payouts')
            ->assertStatus(403);
    }

    public function test_manager_can_list_payouts(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->getJson('/api/admin/payouts')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page']);
    }

    public function test_share_sum_over_100_is_rejected(): void
    {
        $admin = $this->userWithRole('super_admin');
        $collection = Collection::factory()->partner()->create();
        RevenueShare::factory()->create(['collection_id' => $collection->id, 'percentage' => 70]);

        $this->actingAs($admin)
            ->postJson("/api/admin/collections/{$collection->id}/revenue-shares", $this->shareData(['percentage' => 40]))
            ->assertStatus(422);

        // Rolled back — the offending share was not persisted.
        $this->assertSame(1, $collection->revenueShares()->count());
    }

    public function test_super_admin_can_mark_payout_paid(): void
    {
        $admin = $this->userWithRole('super_admin');
        $collection = Collection::factory()->partner()->create();
        $payout = PayoutEntry::create([
            'order_id' => \App\Models\Order::factory()->create()->id,
            'collection_id' => $collection->id,
            'gross_amount' => 250,
            'commission_amount' => 75,
            'net_amount' => 175,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->postJson("/api/admin/payouts/{$payout->id}/mark-paid", ['payout_reference' => 'MM-123'])
            ->assertStatus(200);

        $this->assertDatabaseHas('payout_entries', ['id' => $payout->id, 'status' => 'paid', 'payout_reference' => 'MM-123']);
    }

    public function test_manager_cannot_mark_payout_paid(): void
    {
        $manager = $this->userWithRole('manager');
        $collection = Collection::factory()->partner()->create();
        $payout = PayoutEntry::create([
            'order_id' => \App\Models\Order::factory()->create()->id,
            'collection_id' => $collection->id,
            'gross_amount' => 250, 'commission_amount' => 75, 'net_amount' => 175, 'status' => 'pending',
        ]);

        $this->actingAs($manager)
            ->postJson("/api/admin/payouts/{$payout->id}/mark-paid")
            ->assertStatus(403);
    }
}
