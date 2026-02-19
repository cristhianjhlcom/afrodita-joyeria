<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        Brand::factory()
            ->count(3)
            ->create()
            ->each(function (Brand $brand): void {
                BrandWhitelist::query()->create([
                    'brand_id' => $brand->id,
                    'enabled' => true,
                ]);

                $parentCategory = Category::factory()->create();
                $subcategory = Category::factory()->create([
                    'parent_id' => $parentCategory->id,
                ]);

                Product::factory()
                    ->count(4)
                    ->create([
                        'brand_id' => $brand->id,
                        'subcategory_id' => $subcategory->id,
                    ])
                    ->each(function (Product $product) use ($subcategory): void {
                        $product->categories()->syncWithoutDetaching([$subcategory->id]);

                        ProductVariant::factory()
                            ->count(2)
                            ->create([
                                'product_id' => $product->id,
                            ])
                            ->each(function (ProductVariant $variant) use ($product): void {
                                ProductImage::factory()->count(2)->create([
                                    'product_id' => $product->id,
                                    'product_variant_id' => $variant->id,
                                ]);
                            });
                    });
            });
    }
}
