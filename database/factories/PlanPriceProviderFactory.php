<?php

namespace Database\Factories;

use App\Models\PlanPrice;
use App\Models\PlanPriceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPriceProvider>
 */
class PlanPriceProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_price_id' => PlanPrice::factory(),
            'provider' => fake()->randomElement(['stripe', 'appstore', 'playstore', 'oxapay']),
            'external_id' => fake()->uuid(),
            'is_active' => true,
        ];
    }
}
