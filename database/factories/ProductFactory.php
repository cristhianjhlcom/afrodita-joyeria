<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
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
            'category_id' => Category::factory(),
            'subcategory_id' => Subcategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'description' => fake()->paragraph(),
            'status' => Product::STATUS_DRAFT,
            'sort_order' => fake()->numberBetween(0, 20),
            'url' => fake()->url(),
            'featured_image' => fake()->imageUrl(),
            'youtube_video_id' => fake()->optional()->regexify('[A-Za-z0-9_-]{11}'),
            'remote_updated_at' => now(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Product::STATUS_PUBLISHED,
        ]);
    }

    public function inStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Product::STATUS_IN_STOCK,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Product::STATUS_OUT_OF_STOCK,
        ]);
    }
}
