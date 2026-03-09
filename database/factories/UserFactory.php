<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gabonPrefixes = ['077', '066', '074', '062', '065'];
        $phone = fake()->randomElement($gabonPrefixes) . fake()->numerify('######');

        return [
            'role_id' => Role::where('slug', 'customer')->value('id') ?? 2,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+241' . $phone,
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * User with manager role.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::where('slug', 'manager')->value('id') ?? 3,
        ]);
    }

    /**
     * Inactive user.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Unverified user.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }
}
