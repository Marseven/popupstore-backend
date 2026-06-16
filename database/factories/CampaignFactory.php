<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'status' => 'active',
            'sales_goal' => null,
            'settings' => ['points_rule' => 'per_item'],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }

    public function past(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subMonth(), 'ends_at' => now()->subWeek()]);
    }
}
