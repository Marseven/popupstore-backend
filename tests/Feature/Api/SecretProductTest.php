<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
    }

    private function secretProduct(): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'is_secret' => true,
            'slug' => 'produit-secret',
            'name' => 'Produit Secret',
        ]);
    }

    public function test_guest_does_not_see_secret_product_in_listing(): void
    {
        $this->secretProduct();
        Product::factory()->create(['is_active' => true, 'is_secret' => false, 'name' => 'Produit Public']);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Produit Public', $names);
        $this->assertNotContains('Produit Secret', $names);
    }

    public function test_authenticated_user_sees_secret_product_in_listing(): void
    {
        $this->secretProduct();
        $user = User::factory()->create();

        $names = collect($this->actingAs($user)->getJson('/api/products')->json('data'))->pluck('name');

        $this->assertContains('Produit Secret', $names);
    }

    public function test_guest_cannot_open_secret_product_page(): void
    {
        $this->secretProduct();

        $this->getJson('/api/products/produit-secret')->assertStatus(404);
    }

    public function test_authenticated_user_can_open_secret_product_page(): void
    {
        $this->secretProduct();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/products/produit-secret')
            ->assertOk()
            ->assertJsonPath('product.name', 'Produit Secret');
    }

    public function test_guest_featured_excludes_secret_products(): void
    {
        Product::factory()->create([
            'is_active' => true, 'is_secret' => true, 'is_featured' => true, 'name' => 'Secret Featured',
        ]);

        $names = collect($this->getJson('/api/products/featured')->json('products'))->pluck('name');

        $this->assertNotContains('Secret Featured', $names);
    }
}
