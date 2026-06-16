<?php

namespace Database\Factories;

use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MerchantProfile>
 */
class MerchantProfileFactory extends Factory
{
    protected $model = MerchantProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_name' => fake()->company(),
            'rccm_nif' => null,
            'payout_phone' => '+24107'.fake()->numerify('#######'),
            'payout_provider' => fake()->randomElement(['airtelmoney', 'moovmoney4']),
            'status' => 'pending',
        ];
    }
}
