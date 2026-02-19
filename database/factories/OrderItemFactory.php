<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(1000, 50000);
        $qty = fake()->numberBetween(1, 5);

        return [
            'order_id' => Order::factory(),
            'variant_external_id' => fake()->numberBetween(1, 999999),
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'name_snapshot' => fake()->words(3, true),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $qty * $unitPrice,
        ];
    }
}
