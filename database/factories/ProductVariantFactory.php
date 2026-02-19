<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(1000, 100000);

        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'code' => strtoupper(fake()->unique()->bothify('VAR-####')),
            'price' => $price,
            'sale_price' => fake()->boolean(30) ? $price - fake()->numberBetween(100, 900) : null,
            'color' => fake()->safeColorName(),
            'hex' => fake()->hexColor(),
            'size' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL']),
            'stock_on_hand' => fake()->numberBetween(0, 100),
            'stock_reserved' => fake()->numberBetween(0, 5),
            'stock_available' => fake()->numberBetween(0, 95),
            'is_active' => true,
        ];
    }
}
