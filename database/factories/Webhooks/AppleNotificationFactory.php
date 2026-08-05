<?php

namespace Database\Factories\Webhooks;

use App\Enum\AppleNotificationSubtype;
use App\Enum\AppleNotificationType;
use App\Models\Webhooks\AppleNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppleNotification>
 */
class AppleNotificationFactory extends Factory
{
    protected $model = AppleNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalTransactionId = (string) fake()->unique()->numerify('#################');

        return [
            'notification_type' => AppleNotificationType::DidRenew->value,
            'subtype' => null,
            'notification_uuid' => fake()->unique()->uuid(),
            'version' => '2.0',
            'signed_date' => now(),
            'payload' => ['data' => ['environment' => 'Production']],
            'transaction_info' => ['originalTransactionId' => $originalTransactionId],
            'renewal_info' => null,
            'app_account_token' => fake()->uuid(),
            'original_transaction_id' => $originalTransactionId,
            'transaction_id' => (string) fake()->unique()->numerify('#################'),
            'product_id' => 'com.example.app.monthly',
            'processed' => false,
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'processed' => true,
            'processed_at' => now(),
        ]);
    }

    public function subtype(AppleNotificationSubtype $subtype): static
    {
        return $this->state(fn (array $attributes): array => ['subtype' => $subtype->value]);
    }
}
