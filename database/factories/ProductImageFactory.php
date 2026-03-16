<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'path' => 'products/' . fake()->uuid() . '.jpg',
            'alt_text' => fake()->sentence(3),
            'is_primary' => false,
            'sort_order' => fake()->numberBetween(0, 3),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true, 'sort_order' => 0]);
    }
}
