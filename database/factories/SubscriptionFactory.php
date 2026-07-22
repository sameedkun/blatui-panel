<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->app(),
            'plan_id' => Plan::factory(),
            'plan_price_id' => PlanPrice::factory(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'amount_paid' => fake()->randomFloat(2, 5, 100),
            'currency' => 'USD',
            'status' => 'active',
            'cancelled_by' => null,
            'cancelled_reason' => null,
            'is_recurring' => true,
            'provider' => 'local',
        ];
    }

    /** A subscription currently in its trial period. */
    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(7),
        ]);
    }

    /** A subscription cancelled by the given actor. */
    public function cancelled(string $by = 'user'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'is_recurring' => false,
            'cancelled_by' => $by,
            'cancelled_reason' => fake()->sentence(),
            'ends_at' => now()->subDay(),
        ]);
    }

    /** A subscription that has expired. */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'is_recurring' => false,
            'ends_at' => now()->subDay(),
        ]);
    }
}
