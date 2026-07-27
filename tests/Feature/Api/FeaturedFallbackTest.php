<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FeaturedFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_falls_back_to_latest_when_no_featured(): void
    {
        $cat = ProductCategory::factory()->create();
        Product::factory()->count(6)->create(['is_active' => true, 'is_featured' => false, 'category_id' => $cat->id]);

        $this->getJson('/api/products/featured')
            ->assertOk()
            ->assertJsonCount(5, 'products'); // the 5 latest fill the section
    }

    public function test_tops_up_partial_featured_to_five(): void
    {
        $cat = ProductCategory::factory()->create();
        Product::factory()->count(2)->create(['is_active' => true, 'is_featured' => true, 'category_id' => $cat->id]);
        Product::factory()->count(4)->create(['is_active' => true, 'is_featured' => false, 'category_id' => $cat->id]);

        $this->getJson('/api/products/featured')
            ->assertOk()
            ->assertJsonCount(5, 'products'); // 2 featured + 3 latest
    }

    public function test_keeps_all_featured_when_enough(): void
    {
        $cat = ProductCategory::factory()->create();
        Product::factory()->count(6)->create(['is_active' => true, 'is_featured' => true, 'category_id' => $cat->id]);
        Product::factory()->count(3)->create(['is_active' => true, 'is_featured' => false, 'category_id' => $cat->id]);

        $this->getJson('/api/products/featured')
            ->assertOk()
            ->assertJsonCount(6, 'products'); // all 6 featured, no fill
    }
}
