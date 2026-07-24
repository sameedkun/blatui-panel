<?php

namespace Database\Factories;

use App\Enum\FeedbackStatus;
use App\Enum\FeedbackType;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'email' => fake()->safeEmail(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'type' => fake()->randomElement(FeedbackType::cases())->value,
            'status' => FeedbackStatus::New->value,
            'admin_notes' => null,
            'read_at' => null,
            'resolved_at' => null,
        ];
    }

    /** Submitted while signed in — attached to a real account. */
    public function fromUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FeedbackStatus::Read,
            'read_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FeedbackStatus::Resolved,
            'read_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    public function ignored(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FeedbackStatus::Ignored,
            'read_at' => now(),
        ]);
    }
}
