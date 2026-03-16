<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Super Admin', 'Manager', 'Customer']),
            'slug' => fn (array $attributes) => str($attributes['name'])->slug('_')->toString(),
            'description' => fake()->sentence(),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'name' => 'Super Admin',
            'slug' => 'super_admin',
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn () => [
            'name' => 'Manager',
            'slug' => 'manager',
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn () => [
            'name' => 'Customer',
            'slug' => 'customer',
        ]);
    }
}
