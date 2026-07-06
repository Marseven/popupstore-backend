<?php

namespace Tests\Feature\Api;

use App\Models\MediaContent;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_link_multiple_media(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $audio = MediaContent::factory()->create(['type' => 'audio']);
        $video = MediaContent::factory()->create(['type' => 'video']);

        $product->mediaContents()->sync([$audio->id => ['sort_order' => 0], $video->id => ['sort_order' => 1]]);

        $this->assertCount(2, $product->fresh()->mediaContents);
    }

    public function test_public_media_hub_returns_product_media(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'slug' => 'eddg-tee']);
        $audio = MediaContent::factory()->create(['type' => 'audio', 'title' => 'EDDG Track']);
        $video = MediaContent::factory()->create(['type' => 'video', 'title' => 'EDDG Video']);
        $product->mediaContents()->sync([$audio->id => ['sort_order' => 0], $video->id => ['sort_order' => 1]]);

        $this->getJson('/api/products/eddg-tee/media')
            ->assertOk()
            ->assertJsonPath('product.slug', 'eddg-tee')
            ->assertJsonCount(2, 'media')
            ->assertJsonPath('media.0.title', 'EDDG Track')
            ->assertJsonPath('media.1.type', 'video');
    }

    public function test_media_hub_404_for_unknown_product(): void
    {
        $this->getJson('/api/products/nope/media')->assertStatus(404);
    }
}
