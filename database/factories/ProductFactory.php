<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $price = fake()->numberBetween(5000, 50000);

        return [
            'category_id' => ProductCategory::factory(),
            'collection_id' => null,
            'media_content_id' => null,
            'sku' => 'POP-' . strtoupper(Str::random(8)),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'price' => $price,
            'compare_price' => fake()->optional(0.3)->numberBetween($price + 1000, $price + 10000),
            'cost_price' => fake()->numberBetween(1000, max(1001, $price - 1000)),
            'is_active' => true,
            'is_featured' => fake()->boolean(20),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
