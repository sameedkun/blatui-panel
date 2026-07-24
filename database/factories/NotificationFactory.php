<?php

namespace Database\Factories;

use App\Enum\NotificationPushStatus;
use App\Enum\NotificationType;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'type' => fake()->randomElement(NotificationType::cases())->value,
            'link' => null,
            'push_status' => NotificationPushStatus::Draft->value,
            'push_sent_at' => null,
            'push_error' => null,
            'onesignal_notification_id' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'push_status' => NotificationPushStatus::Sent,
            'push_sent_at' => now(),
            'onesignal_notification_id' => fake()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'push_status' => NotificationPushStatus::Failed,
            'push_error' => 'OneSignal Error: Invalid app_id.',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'push_status' => NotificationPushStatus::Pending,
        ]);
    }
}
