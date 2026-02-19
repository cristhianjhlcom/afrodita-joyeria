<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'external_id' => fake()->unique()->numberBetween(1, 999999),
            'brand_id' => Brand::factory(),
            'subcategory_id' => Category::factory(),
            'name' => Str::limit($name, 70, ''),
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'description' => fake()->paragraph(),
            'status' => Product::STATUS_DRAFT,
        ];
    }
}
