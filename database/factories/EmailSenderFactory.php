<?php

namespace Database\Factories;

use App\Enum\MailPurpose;
use App\Models\EmailSender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSender>
 */
class EmailSenderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purpose = fake()->randomElement(MailPurpose::cases());

        return [
            'key' => $purpose,
            'label' => $purpose->label(),
            'email_domain_id' => null,
            'local_part' => 'noreply',
            'from_name' => fake()->company(),
            'is_enabled' => true,
        ];
    }
}
