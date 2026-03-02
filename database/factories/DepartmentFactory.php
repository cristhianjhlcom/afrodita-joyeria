<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'country_id' => Country::factory(),
            'name' => fake()->city(),
            'ubigeo_code' => fake()->unique()->numerify('##'),
            'remote_updated_at' => now(),
        ];
    }
}
