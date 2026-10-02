<?php

namespace Database\Factories;

use App\Enum\TransactionType;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionTransaction>
 */
class SubscriptionTransactionFactory extends Factory
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
            'type' => TransactionType::Initial,
            'amount_minor' => fake()->numberBetween(499, 9999),
            'currency' => 'USD',
            'purchased_at' => now(),
            'provider_transaction_id' => fake()->uuid(),
            'provider_original_id' => fake()->uuid(),
            'payload' => ['status' => 'succeeded'],
        ];
    }

    /** A charge of exactly `$minor` minor units of `$currency`. */
    public function amount(int $minor, string $currency = 'USD'): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_minor' => $minor,
            'currency' => $currency,
        ]);
    }

    public function renewal(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TransactionType::Renewal]);
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TransactionType::Refund]);
    }
}
