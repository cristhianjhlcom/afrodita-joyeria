<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BrandWhitelist>
 */
class BrandWhitelistFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'enabled' => true,
            'main_store_token' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => false,
        ]);
    }

    public function withMainStoreToken(?string $token = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'main_store_token' => $token ?? '1|test-main-store-token',
        ]);
    }
}
