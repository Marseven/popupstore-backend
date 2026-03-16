<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'transaction_id' => 'TXN-' . fake()->uuid(),
            'provider' => fake()->randomElement(['airtel', 'moov']),
            'payment_system' => 'MOBILE_MONEY',
            'phone' => '+241077' . fake()->numerify('######'),
            'amount' => fake()->numberBetween(5000, 100000),
            'currency' => 'XAF',
            'status' => 'pending',
            'provider_response' => null,
            'error_message' => null,
            'initiated_at' => now(),
            'completed_at' => null,
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => 'success',
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => 'Payment declined',
            'completed_at' => now(),
        ]);
    }
}
