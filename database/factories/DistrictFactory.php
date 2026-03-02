<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Department;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\District>
 */
class DistrictFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'country_id' => Country::factory(),
            'department_id' => Department::factory(),
            'province_id' => Province::factory(),
            'name' => fake()->city(),
            'ubigeo_code' => fake()->unique()->numerify('######'),
            'shipping_price' => 2000,
            'has_delivery_express' => false,
            'is_active' => true,
            'remote_updated_at' => now(),
        ];
    }
}
