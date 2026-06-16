<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\RevenueShare;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RevenueShare>
 */
class RevenueShareFactory extends Factory
{
    protected $model = RevenueShare::class;

    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory()->partner(),
            'beneficiary_label' => fake()->company(),
            'payout_phone' => '+24107'.fake()->numerify('#######'),
            'payout_provider' => fake()->randomElement(['airtelmoney', 'moovmoney4']),
            'percentage' => 70,
        ];
    }
}
