<?php

namespace Database\Factories;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => null,
            'product_id' => Product::factory(),
            'size_id' => Size::factory(),
            'quantity' => fake()->numberBetween(1, 5),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'session_id' => 'sess_' . fake()->uuid(),
        ]);
    }
}
