<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductStock>
 */
class ProductStockFactory extends Factory
{
    protected $model = ProductStock::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'size_id' => Size::factory(),
            'quantity' => fake()->numberBetween(0, 100),
            'low_stock_threshold' => 5,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['quantity' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['quantity' => 3, 'low_stock_threshold' => 5]);
    }
}
