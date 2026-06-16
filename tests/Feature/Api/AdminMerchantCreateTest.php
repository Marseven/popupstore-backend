<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMerchantCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role_id' => Role::factory()->create(['slug' => 'super_admin', 'name' => 'SA'])->id,
        ]);
    }

    public function test_admin_can_onboard_a_merchant_by_email(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create([
            'email' => 'shop@example.com',
            'role_id' => Role::where('slug', 'customer')->first()->id,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/admin/merchants', [
                'email' => 'shop@example.com',
                'business_name' => 'Studio Libreville',
                'payout_phone' => '+24107000000',
                'payout_provider' => 'airtelmoney',
            ])
            ->assertStatus(201);

        $this->assertSame('merchant', $target->fresh()->role->slug);
        $this->assertSame(1, $target->ownedCollections()->count());
        $this->assertDatabaseHas('merchant_profiles', ['user_id' => $target->id, 'status' => 'approved']);
    }

    public function test_onboard_unknown_email_returns_404(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->postJson('/api/admin/merchants', [
                'email' => 'nobody@example.com',
                'business_name' => 'X', 'payout_phone' => '+24107000000', 'payout_provider' => 'airtelmoney',
            ])
            ->assertStatus(404);
    }

    public function test_manager_cannot_onboard_merchant(): void
    {
        $manager = User::factory()->create([
            'role_id' => Role::factory()->create(['slug' => 'manager', 'name' => 'Mgr'])->id,
        ]);

        $this->actingAs($manager)
            ->postJson('/api/admin/merchants', [
                'email' => 'x@example.com',
                'business_name' => 'X', 'payout_phone' => '+24107000000', 'payout_provider' => 'airtelmoney',
            ])
            ->assertStatus(403);
    }
}
