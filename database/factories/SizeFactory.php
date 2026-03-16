<?php

namespace Database\Factories;

use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Size>
 */
class SizeFactory extends Factory
{
    protected $model = Size::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL']),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
