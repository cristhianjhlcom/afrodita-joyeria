<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Brand>
 */
class BrandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
