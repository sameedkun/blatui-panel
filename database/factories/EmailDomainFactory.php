<?php

namespace Database\Factories;

use App\Models\EmailDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailDomain>
 */
class EmailDomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'domain' => fake()->unique()->domainName(),
            'description' => fake()->optional()->sentence(),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
