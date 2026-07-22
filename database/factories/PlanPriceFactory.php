<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'amount' => fake()->randomFloat(2, 5, 100),
            'compare_at_amount' => null,
            'currency' => 'USD',
            'billing_period' => 1,
            'billing_interval' => 'month',
            'trial_period' => 0,
            'trial_interval' => 'day',
            'grace_period' => 0,
            'grace_interval' => 'day',
            'is_active' => true,
        ];
    }

    /** A yearly billed price. */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_period' => 1,
            'billing_interval' => 'year',
        ]);
    }

    /** A price with a trial period attached. */
    public function withTrial(int $days = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_period' => $days,
            'trial_interval' => 'day',
        ]);
    }
}
