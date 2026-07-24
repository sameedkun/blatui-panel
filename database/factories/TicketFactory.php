<?php

namespace Database\Factories;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->app(),
            'category_id' => TicketCategory::factory(),
            'assigned_to' => null,
            'subject' => fake()->sentence(6),
            'status' => fake()->randomElement(TicketStatus::cases())->value,
            'priority' => fake()->randomElement(TicketPriority::cases())->value,
            'last_user_response_at' => now(),
        ];
    }

    /** Assigned to a specific agent. */
    public function assignedTo(User $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $agent->id,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TicketStatus::Open->value]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Closed->value,
            'closed_at' => now(),
        ]);
    }
}
