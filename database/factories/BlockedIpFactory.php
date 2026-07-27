<?php

namespace Database\Factories;

use App\Models\BlockedIp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockedIp>
 */
class BlockedIpFactory extends Factory
{
    protected $model = BlockedIp::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ip_address' => fake()->unique()->ipv4(),
            'user_id' => null,
            'reason' => fake()->sentence(),
            'blocked_by' => User::factory()->state(['type' => 'staff']),
            'hits' => 0,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => null]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => $user->id]);
    }

    public function permanent(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => null]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subDay()]);
    }
}
