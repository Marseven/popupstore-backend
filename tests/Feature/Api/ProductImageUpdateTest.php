<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::factory()->customer()->create();

        return User::factory()->create([
            'role_id' => Role::factory()->create(['slug' => 'super_admin', 'name' => 'SA'])->id,
        ]);
    }

    public function test_replacing_images_keeps_total_within_four(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        // 3 existing images
        $imgs = collect(range(1, 3))->map(fn ($i) => ProductImage::factory()->create([
            'product_id' => $product->id, 'path' => "products/old{$i}.jpg", 'sort_order' => $i,
        ]));

        // Keep only the first, add 2 new → final should be 3 (1 + 2), well under 4.
        $response = $this->actingAs($this->admin())->post("/api/admin/products/{$product->id}", [
            '_method' => 'PUT',
            'existing_images' => json_encode([$imgs[0]->id]),
            'images' => [UploadedFile::fake()->image('n1.jpg'), UploadedFile::fake()->image('n2.jpg')],
        ]);

        $response->assertOk();
        $this->assertSame(3, $product->fresh()->images()->count());
        // the two dropped images are gone
        $this->assertDatabaseMissing('product_images', ['id' => $imgs[1]->id]);
        $this->assertDatabaseMissing('product_images', ['id' => $imgs[2]->id]);
        $this->assertDatabaseHas('product_images', ['id' => $imgs[0]->id]);
    }

    public function test_rejects_when_kept_plus_new_exceeds_four(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $imgs = collect(range(1, 3))->map(fn ($i) => ProductImage::factory()->create([
            'product_id' => $product->id, 'path' => "products/old{$i}.jpg", 'sort_order' => $i,
        ]));

        // Keep all 3 + add 2 → 5 > 4 → must be rejected.
        $this->actingAs($this->admin())->post("/api/admin/products/{$product->id}", [
            '_method' => 'PUT',
            'existing_images' => json_encode($imgs->pluck('id')->all()),
            'images' => [UploadedFile::fake()->image('n1.jpg'), UploadedFile::fake()->image('n2.jpg')],
        ])->assertStatus(422);
    }
}
