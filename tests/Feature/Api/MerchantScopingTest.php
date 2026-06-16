<?php

namespace Tests\Feature\Api;

use App\Models\Collection;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutEntry;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
    }

    /** An approved merchant with a partner collection they own. */
    private function approvedMerchant(): User
    {
        $role = Role::firstOrCreate(['slug' => 'merchant'], ['name' => 'Marchand']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        MerchantProfile::factory()->create(['user_id' => $user->id, 'status' => 'approved', 'approved_at' => now()]);
        Collection::factory()->partner()->create(['owner_user_id' => $user->id]);

        return $user;
    }

    private function productFor(User $merchant): Product
    {
        $collectionId = $merchant->ownedCollections()->first()->id;

        return Product::factory()->create(['collection_id' => $collectionId]);
    }

    public function test_merchant_sees_only_their_products(): void
    {
        $a = $this->approvedMerchant();
        $b = $this->approvedMerchant();
        $aProduct = $this->productFor($a);
        $bProduct = $this->productFor($b);

        $res = $this->actingAs($a)->getJson('/api/merchant/products')->assertStatus(200);

        $ids = collect($res->json('data'))->pluck('id');
        $this->assertContains($aProduct->id, $ids);
        $this->assertNotContains($bProduct->id, $ids);
    }

    public function test_merchant_cannot_update_another_merchants_product(): void
    {
        $a = $this->approvedMerchant();
        $b = $this->approvedMerchant();
        $bProduct = $this->productFor($b);

        $this->actingAs($a)
            ->putJson("/api/merchant/products/{$bProduct->id}", ['name' => 'Hijack'])
            ->assertStatus(404);

        $this->assertDatabaseMissing('products', ['id' => $bProduct->id, 'name' => 'Hijack']);
    }

    public function test_merchant_cannot_create_product_in_foreign_collection(): void
    {
        $a = $this->approvedMerchant();
        $b = $this->approvedMerchant();
        $bCollection = $b->ownedCollections()->first();

        $this->actingAs($a)
            ->postJson('/api/merchant/products', [
                'collection_id' => $bCollection->id,
                'name' => 'X', 'price' => 1000,
            ])
            ->assertStatus(403);
    }

    public function test_merchant_sees_only_orders_with_their_products(): void
    {
        $a = $this->approvedMerchant();
        $b = $this->approvedMerchant();

        $orderA = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $orderA->id, 'product_id' => $this->productFor($a)->id]);

        $orderB = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $orderB->id, 'product_id' => $this->productFor($b)->id]);

        $res = $this->actingAs($a)->getJson('/api/merchant/orders')->assertStatus(200);

        $ids = collect($res->json('data'))->pluck('id');
        $this->assertContains($orderA->id, $ids);
        $this->assertNotContains($orderB->id, $ids);
    }

    public function test_merchant_sees_only_their_payouts(): void
    {
        $a = $this->approvedMerchant();
        $b = $this->approvedMerchant();

        $payoutA = PayoutEntry::create([
            'order_id' => Order::factory()->create()->id,
            'collection_id' => $a->ownedCollections()->first()->id,
            'gross_amount' => 250, 'commission_amount' => 75, 'net_amount' => 175, 'status' => 'pending',
        ]);
        $payoutB = PayoutEntry::create([
            'order_id' => Order::factory()->create()->id,
            'collection_id' => $b->ownedCollections()->first()->id,
            'gross_amount' => 250, 'commission_amount' => 75, 'net_amount' => 175, 'status' => 'pending',
        ]);

        $res = $this->actingAs($a)->getJson('/api/merchant/payouts')->assertStatus(200);

        $ids = collect($res->json('data'))->pluck('id');
        $this->assertContains($payoutA->id, $ids);
        $this->assertNotContains($payoutB->id, $ids);
    }

    public function test_non_merchant_cannot_access_merchant_area(): void
    {
        $customer = User::factory()->create(['role_id' => Role::where('slug', 'customer')->first()->id]);

        $this->actingAs($customer)->getJson('/api/merchant/products')->assertStatus(403);
    }

    public function test_suspended_merchant_is_blocked(): void
    {
        $role = Role::firstOrCreate(['slug' => 'merchant'], ['name' => 'Marchand']);
        $user = User::factory()->create(['role_id' => $role->id]);
        MerchantProfile::factory()->create(['user_id' => $user->id, 'status' => 'suspended']);

        $this->actingAs($user)->getJson('/api/merchant/dashboard')->assertStatus(403);
    }

    public function test_apply_creates_pending_profile(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('slug', 'customer')->first()->id]);

        $this->actingAs($user)->postJson('/api/merchant/apply', [
            'business_name' => 'Studio Gabon',
            'payout_phone' => '+24107000000',
            'payout_provider' => 'airtelmoney',
        ])->assertStatus(201);

        $this->assertDatabaseHas('merchant_profiles', ['user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_approve_assigns_role_and_creates_owned_collection(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::factory()->create(['slug' => 'super_admin', 'name' => 'SA'])->id,
        ]);
        $applicant = User::factory()->create(['role_id' => Role::where('slug', 'customer')->first()->id]);
        $profile = MerchantProfile::factory()->create(['user_id' => $applicant->id, 'status' => 'pending']);

        $this->actingAs($superAdmin)
            ->postJson("/api/admin/merchants/{$profile->id}/approve")
            ->assertStatus(200);

        $this->assertSame('merchant', $applicant->fresh()->role->slug);
        $this->assertSame(1, $applicant->ownedCollections()->count());
        $this->assertDatabaseHas('merchant_profiles', ['id' => $profile->id, 'status' => 'approved']);
    }
}
