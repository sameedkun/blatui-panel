<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionReceipt>
 */
class SubscriptionReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'provider' => 'stripe',
            'type' => 'initial',
            'provider_transaction_id' => fake()->uuid(),
            'provider_original_id' => fake()->uuid(),
            'payload' => ['status' => 'succeeded'],
        ];
    }
}
