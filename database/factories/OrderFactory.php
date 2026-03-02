<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 100000);

        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'main_store_external_order_id' => null,
            'external_customer_id' => fake()->numberBetween(1, 99999),
            'status' => fake()->randomElement(['pending', 'paid', 'shipped', 'completed']),
            'currency' => 'USD',
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $subtotal,
            'placed_at' => now(),
            'cancellation_note' => null,
            'is_refunded' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'paid',
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'shipped',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
        ]);
    }
}
