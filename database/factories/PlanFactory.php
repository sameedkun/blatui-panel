<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Plan',
            'description' => fake()->optional()->sentence(),
            'features' => fake()->words(3),
            'is_active' => true,
            'is_best_deal' => false,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    /** An inactive plan, hidden from purchase. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /** The plan highlighted as the best deal. */
    public function bestDeal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_best_deal' => true,
        ]);
    }
}
