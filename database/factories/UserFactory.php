<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'banned_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'ban_reason' => fake()->optional()->sentence(),
            'registration_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'last_login' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'apple_id' => fake()->optional()->uuid(),
            'google_id' => fake()->optional()->uuid(),
            'type' => fake()->randomElement(['app', 'guest']),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
