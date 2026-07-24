<?php

namespace Database\Factories;

use App\Enum\TicketMessageAuthorType;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    protected $model = TicketMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory()->app(),
            'author_type' => TicketMessageAuthorType::User->value,
            'message' => fake()->paragraph(),
        ];
    }

    public function fromStaff(User $staff): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $staff->id,
            'author_type' => TicketMessageAuthorType::Staff->value,
        ]);
    }

    public function system(string $message): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'author_type' => TicketMessageAuthorType::System->value,
            'message' => $message,
        ]);
    }
}
