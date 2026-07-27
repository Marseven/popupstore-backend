<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Role;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFormCreateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::factory()->customer()->create();

        return User::factory()->create([
            'role_id' => Role::factory()->create(['slug' => 'super_admin', 'name' => 'SA'])->id,
        ]);
    }

    public function test_creates_product_without_sku_and_with_size_name_stocks(): void
    {
        Size::factory()->create(['name' => 'M']);

        // Exactly what the admin form sends: no sku, stocks as a JSON string of {size_name, quantity}.
        $response = $this->actingAs($this->admin())->postJson('/api/admin/products', [
            'name' => 'T-shirt EDDG — 17 Août',
            'price' => 12000,
            'stocks' => json_encode([
                ['size_name' => 'M', 'quantity' => 50],
                ['size_name' => 'L', 'quantity' => 50],
            ]),
        ]);

        $response->assertStatus(201);

        $product = Product::where('name', 'T-shirt EDDG — 17 Août')->first();
        $this->assertNotNull($product);
        $this->assertStringStartsWith('POP-', $product->sku);            // auto-generated
        $this->assertSame(2, $product->stocks()->count());               // both sizes created
        $this->assertTrue(Size::where('name', 'L')->exists());           // new size auto-created
    }

    public function test_update_syncs_stocks_by_size_name(): void
    {
        $product = Product::factory()->create();
        $m = Size::factory()->create(['name' => 'M']);
        $product->stocks()->create(['size_id' => $m->id, 'quantity' => 10]);

        $this->actingAs($this->admin())->postJson("/api/admin/products/{$product->id}", [
            '_method' => 'PUT',
            'name' => $product->name,
            'stocks' => json_encode([
                ['size_name' => 'M', 'quantity' => 99],
                ['size_name' => 'XL', 'quantity' => 5],
            ]),
        ])->assertOk();

        $this->assertSame(2, $product->fresh()->stocks()->count());
        $this->assertDatabaseHas('product_stock', ['product_id' => $product->id, 'size_id' => $m->id, 'quantity' => 99]);
    }
}
