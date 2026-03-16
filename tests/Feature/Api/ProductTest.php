<?php

namespace Tests\Feature\Api;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
    }

    // ---------------------------------------------------------------
    // Index (paginated listing)
    // ---------------------------------------------------------------

    public function test_index_returns_paginated_products(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total']);
    }

    public function test_index_only_returns_active_products(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->count(2)->inactive()->create();

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_category_slug(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 't-shirts']);
        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        Product::factory()->count(2)->create(['is_active' => true]);

        $response = $this->getJson('/api/products?category=t-shirts');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_search_term(): void
    {
        Product::factory()->create([
            'name' => 'Exclusive Vinyl Record',
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Basic T-Shirt',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products?search=Vinyl');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Exclusive Vinyl Record', $response->json('data.0.name'));
    }

    public function test_index_filters_by_price_range(): void
    {
        Product::factory()->create(['price' => 5000, 'is_active' => true]);
        Product::factory()->create(['price' => 15000, 'is_active' => true]);
        Product::factory()->create(['price' => 30000, 'is_active' => true]);

        $response = $this->getJson('/api/products?min_price=10000&max_price=20000');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_paginates_with_custom_per_page(): void
    {
        Product::factory()->count(10)->create(['is_active' => true]);

        $response = $this->getJson('/api/products?per_page=3');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        $this->assertEquals(3, $response->json('per_page'));
    }

    public function test_index_caps_per_page_at_50(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);

        $response = $this->getJson('/api/products?per_page=100');

        $response->assertOk();
        $this->assertEquals(50, $response->json('per_page'));
    }

    public function test_index_sorts_by_price_asc(): void
    {
        Product::factory()->create(['price' => 30000, 'is_active' => true]);
        Product::factory()->create(['price' => 10000, 'is_active' => true]);
        Product::factory()->create(['price' => 20000, 'is_active' => true]);

        $response = $this->getJson('/api/products?sort_by=price&sort_dir=asc');

        $response->assertOk();
        $prices = collect($response->json('data'))->pluck('price')->map(fn($p) => (float) $p)->toArray();
        $this->assertEquals([10000.00, 20000.00, 30000.00], $prices);
    }

    // ---------------------------------------------------------------
    // Show (single product by slug)
    // ---------------------------------------------------------------

    public function test_show_returns_product_by_slug(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'slug' => 'test-product',
        ]);

        $response = $this->getJson('/api/products/test-product');

        $response->assertOk()
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('product.slug', 'test-product');
    }

    public function test_show_returns_404_for_inactive_product(): void
    {
        Product::factory()->create([
            'is_active' => false,
            'slug' => 'hidden-product',
        ]);

        $response = $this->getJson('/api/products/hidden-product');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson('/api/products/does-not-exist');

        $response->assertStatus(404);
    }

    public function test_show_eager_loads_relations(): void
    {
        Product::factory()->create([
            'is_active' => true,
            'slug' => 'loaded-product',
        ]);

        $response = $this->getJson('/api/products/loaded-product');

        $response->assertOk()
            ->assertJsonStructure([
                'product' => ['id', 'name', 'slug', 'price', 'images', 'stocks', 'category'],
            ]);
    }

    // ---------------------------------------------------------------
    // Featured
    // ---------------------------------------------------------------

    public function test_featured_returns_featured_products(): void
    {
        Product::factory()->count(3)->featured()->create(['is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => true, 'is_featured' => false]);

        $response = $this->getJson('/api/products/featured');

        $response->assertOk()
            ->assertJsonStructure(['products']);

        $this->assertCount(3, $response->json('products'));
    }

    public function test_featured_limits_to_8_products(): void
    {
        Product::factory()->count(12)->featured()->create(['is_active' => true]);

        $response = $this->getJson('/api/products/featured');

        $response->assertOk();
        $this->assertLessThanOrEqual(8, count($response->json('products')));
    }

    public function test_featured_excludes_inactive_products(): void
    {
        Product::factory()->count(2)->featured()->create(['is_active' => true]);
        Product::factory()->featured()->inactive()->create();

        $response = $this->getJson('/api/products/featured');

        $response->assertOk();
        $this->assertCount(2, $response->json('products'));
    }

    // ---------------------------------------------------------------
    // By Category
    // ---------------------------------------------------------------

    public function test_by_category_returns_products_for_slug(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'hoodies', 'is_active' => true]);
        Product::factory()->count(4)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products/category/hoodies');

        $response->assertOk()
            ->assertJsonStructure(['category', 'products']);
    }

    public function test_by_category_returns_404_for_inactive_category(): void
    {
        ProductCategory::factory()->inactive()->create(['slug' => 'hidden-cat']);

        $response = $this->getJson('/api/products/category/hidden-cat');

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // By Collection
    // ---------------------------------------------------------------

    public function test_by_collection_returns_products_for_slug(): void
    {
        $collection = Collection::factory()->create(['slug' => 'summer-2025', 'is_active' => true]);
        Product::factory()->count(3)->create([
            'collection_id' => $collection->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products/collection/summer-2025');

        $response->assertOk()
            ->assertJsonStructure(['collection', 'products']);
    }
}
