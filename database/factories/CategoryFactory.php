<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word();

        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'parent_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'is_active' => true,
        ];
    }

    public function subcategory(?Category $parent = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => $parent?->id ?? Category::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
