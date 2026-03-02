<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'name' => fake()->country(),
            'iso_code_2' => fake()->unique()->lexify('??'),
            'iso_code_3' => fake()->unique()->lexify('???'),
            'is_active' => true,
            'remote_updated_at' => now(),
        ];
    }
}
