<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->languageCode().'-'.fake()->word(),
            'native_name' => fake()->word(),
            'code' => fake()->unique()->languageCode(),
            'flag' => null,
            'is_rtl' => false,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
            'translations' => null,
        ];
    }

    /** The default language for the panel. */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /** An inactive/disabled language. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
