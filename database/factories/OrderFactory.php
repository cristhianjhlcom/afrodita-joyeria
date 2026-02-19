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
            'external_customer_id' => fake()->numberBetween(1, 99999),
            'status' => fake()->randomElement(['pending', 'paid', 'shipped', 'completed']),
            'currency' => 'USD',
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $subtotal,
            'placed_at' => now(),
        ];
    }
}
