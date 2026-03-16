<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(5000, 50000);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'size_id' => null,
            'product_name' => fake()->words(3, true),
            'product_sku' => 'POP-' . strtoupper(fake()->bothify('??######')),
            'size_name' => fake()->optional(0.7)->randomElement(['S', 'M', 'L', 'XL']),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total' => $unitPrice * $quantity,
            'media_content_id' => null,
        ];
    }
}
