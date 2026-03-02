<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $stockOnHand = fake()->numberBetween(0, 100);
        $stockReserved = fake()->numberBetween(0, min(10, $stockOnHand));

        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'external_ref' => Str::lower((string) Str::uuid()),
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'code' => strtoupper(fake()->unique()->bothify('VAR-####')),
            'price' => $price,
            'sale_price' => fake()->boolean(30) ? $price - fake()->numberBetween(100, 900) : null,
            'color' => fake()->safeColorName(),
            'hex' => fake()->hexColor(),
            'size' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL']),
            'primary_image_url' => fake()->imageUrl(),
            'stock_on_hand' => $stockOnHand,
            'stock_reserved' => $stockReserved,
            'stock_available' => max(0, $stockOnHand - $stockReserved),
            'is_active' => true,
            'remote_updated_at' => now(),
        ];
    }

    public function onSale(): static
    {
        return $this->state(function (array $attributes): array {
            $price = $attributes['price'] ?? fake()->numberBetween(1000, 100000);

            return [
                'price' => $price,
                'sale_price' => max(100, (int) $price - fake()->numberBetween(100, 1200)),
            ];
        });
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_on_hand' => 0,
            'stock_reserved' => 0,
            'stock_available' => 0,
        ]);
    }

    public function lowStock(): static
    {
        $stockOnHand = fake()->numberBetween(1, 5);
        $stockReserved = fake()->numberBetween(0, min(2, $stockOnHand));

        return $this->state(fn (array $attributes): array => [
            'stock_on_hand' => $stockOnHand,
            'stock_reserved' => $stockReserved,
            'stock_available' => max(0, $stockOnHand - $stockReserved),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
