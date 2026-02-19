<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SyncRun>
 */
class SyncRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource' => fake()->randomElement(['brands', 'categories', 'products']),
            'status' => fake()->randomElement(['running', 'completed', 'failed']),
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'records_processed' => fake()->numberBetween(0, 500),
            'errors_count' => 0,
            'checkpoint_updated_since' => now(),
            'meta' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
            'errors_count' => 0,
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'failed',
            'errors_count' => fake()->numberBetween(1, 5),
            'meta' => [
                'errors' => [
                    fake()->sentence(6),
                ],
            ],
            'finished_at' => now(),
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'running',
            'finished_at' => null,
        ]);
    }
}
