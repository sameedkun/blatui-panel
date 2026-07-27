<?php

namespace Database\Factories;

use App\Enum\DeviceType;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDevice>
 */
class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->app(),
            'name' => fake()->words(2, true),
            'model' => fake()->randomElement(['iPhone 15', 'Pixel 8', 'Galaxy S24']),
            'platform' => fake()->randomElement(['ios', 'android', 'web']),
            'os' => fake()->randomElement(['iOS 17', 'Android 14']),
            'device_type' => fake()->randomElement(DeviceType::cases())->value,
            'app_version' => '1.'.fake()->numberBetween(0, 9).'.0',
            'device_fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'ip_address' => fake()->ipv4(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'last_seen_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
            'token_id' => null,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'blocked_at' => now(),
            'blocked_reason' => 'Blocked for testing.',
            'token_id' => null,
        ]);
    }
}
