<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(5000, 100000);

        return [
            'user_id' => User::factory(),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'shipping_fee' => 0,
            'discount' => 0,
            'total' => $subtotal,
            'shipping_name' => fake()->name(),
            'shipping_phone' => '+241077' . fake()->numerify('######'),
            'shipping_address' => fake()->address(),
            'shipping_city' => fake()->randomElement(['Libreville', 'Port-Gentil', 'Franceville']),
            'payment_method' => fake()->randomElement(['airtel', 'moov']),
            'payment_status' => 'pending',
            'customer_notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => 'success',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'guest_phone' => '+241077' . fake()->numerify('######'),
            'guest_email' => fake()->optional(0.5)->safeEmail(),
            'session_id' => 'sess_' . fake()->uuid(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'payment_status' => 'success',
            'paid_at' => now()->subDays(3),
        ]);
    }
}
