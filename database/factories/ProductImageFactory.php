<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_ref' => Str::lower((string) Str::uuid()),
            'product_id' => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'url' => fake()->imageUrl(),
            'sort_order' => fake()->numberBetween(0, 10),
            'alt' => fake()->sentence(4),
            'is_primary' => fake()->boolean(20),
        ];
    }
}
