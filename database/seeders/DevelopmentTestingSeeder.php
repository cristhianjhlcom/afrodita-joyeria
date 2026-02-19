<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DevelopmentTestingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'dev-admin@afrodita.local',
        ], [
            'name' => 'Dev Admin',
            'password' => bcrypt('password'),
            'role' => UserRole::Admin,
        ]);

        $customers = User::factory()
            ->count(25)
            ->create()
            ->each(function (User $user): void {
                $user->update([
                    'external_customer_id' => fake()->numberBetween(10000, 99999),
                ]);
            });

        $brands = Brand::factory()->count(8)->create();
        $whitelistedBrandIds = $brands->random(6)->pluck('id');

        foreach ($brands as $brand) {
            BrandWhitelist::query()->updateOrCreate([
                'brand_id' => $brand->id,
            ], [
                'enabled' => $whitelistedBrandIds->contains($brand->id),
            ]);
        }

        $parentCategories = Category::factory()->count(4)->create();
        $subcategories = $this->createSubcategories($parentCategories);

        $products = $this->seedProductsForWhitelistedBrands($brands, $whitelistedBrandIds, $subcategories);

        $this->seedOrders($products, $customers);
        $this->seedSyncRuns();
    }

    /**
     * @param  Collection<int, Category>  $parentCategories
     * @return Collection<int, Category>
     */
    private function createSubcategories(Collection $parentCategories): Collection
    {
        return $parentCategories->flatMap(function (Category $parentCategory): Collection {
            return Category::factory()
                ->count(3)
                ->subcategory($parentCategory)
                ->create();
        });
    }

    /**
     * @param  Collection<int, Brand>  $brands
     * @param  Collection<int, int>  $whitelistedBrandIds
     * @param  Collection<int, Category>  $subcategories
     * @return Collection<int, Product>
     */
    private function seedProductsForWhitelistedBrands(Collection $brands, Collection $whitelistedBrandIds, Collection $subcategories): Collection
    {
        $products = collect();

        foreach ($brands->whereIn('id', $whitelistedBrandIds) as $brand) {
            Product::factory()
                ->count(10)
                ->state(fn (array $attributes): array => [
                    'status' => fake()->randomElement([
                        Product::STATUS_PUBLISHED,
                        Product::STATUS_IN_STOCK,
                        Product::STATUS_OUT_OF_STOCK,
                        Product::STATUS_PRE_ORDER,
                    ]),
                    'subcategory_id' => $subcategories->random()->id,
                ])
                ->create([
                    'brand_id' => $brand->id,
                ])
                ->each(function (Product $product) use (&$products): void {
                    $subcategory = Category::query()->find($product->subcategory_id);
                    if ($subcategory !== null) {
                        $categoryIds = [$subcategory->id];
                        if ($subcategory->parent_id !== null) {
                            $categoryIds[] = $subcategory->parent_id;
                        }

                        $product->categories()->syncWithoutDetaching($categoryIds);
                    }

                    $variants = ProductVariant::factory()
                        ->count(fake()->numberBetween(2, 5))
                        ->recycle($product)
                        ->create([
                            'product_id' => $product->id,
                        ]);

                    $this->seedVariantImages($product, $variants);
                    $products->push($product);
                });
        }

        return $products;
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function seedVariantImages(Product $product, Collection $variants): void
    {
        foreach ($variants as $index => $variant) {
            $price = $variant->price ?? fake()->numberBetween(1000, 100000);
            if ($index % 3 === 0) {
                $stockOnHand = fake()->numberBetween(4, 50);
                $stockReserved = fake()->numberBetween(0, min(4, $stockOnHand));
                $variant->update([
                    'price' => $price,
                    'sale_price' => max(100, (int) $price - fake()->numberBetween(100, 1200)),
                    'stock_on_hand' => $stockOnHand,
                    'stock_reserved' => $stockReserved,
                    'stock_available' => max(0, $stockOnHand - $stockReserved),
                ]);
            } elseif ($index % 3 === 1) {
                $stockOnHand = fake()->numberBetween(1, 5);
                $stockReserved = fake()->numberBetween(0, min(2, $stockOnHand));
                $variant->update([
                    'price' => $price,
                    'sale_price' => null,
                    'stock_on_hand' => $stockOnHand,
                    'stock_reserved' => $stockReserved,
                    'stock_available' => max(0, $stockOnHand - $stockReserved),
                ]);
            } else {
                $variant->update([
                    'price' => $price,
                    'sale_price' => null,
                    'stock_on_hand' => 0,
                    'stock_reserved' => 0,
                    'stock_available' => 0,
                ]);
            }

            ProductImage::factory()
                ->count(fake()->numberBetween(1, 3))
                ->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'is_primary' => false,
                ]);

            ProductImage::factory()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, User>  $customers
     */
    private function seedOrders(Collection $products, Collection $customers): void
    {
        $variants = ProductVariant::query()->whereIn('product_id', $products->pluck('id'))->get();

        Order::factory()
            ->count(40)
            ->state(fn (array $attributes): array => [
                'status' => fake()->randomElement(['pending', 'paid', 'shipped', 'completed']),
                'placed_at' => fake()->dateTimeBetween('-30 days'),
            ])
            ->create()
            ->each(function (Order $order) use ($customers, $variants): void {
                $customer = $customers->random();
                $order->update([
                    'external_customer_id' => $customer->external_customer_id,
                ]);

                $itemCount = fake()->numberBetween(1, 4);
                $subtotal = 0;

                for ($i = 0; $i < $itemCount; $i++) {
                    $variant = $variants->random();
                    $qty = fake()->numberBetween(1, 3);
                    $unitPrice = $variant->sale_price ?? $variant->price ?? fake()->numberBetween(1000, 100000);
                    $lineTotal = $qty * $unitPrice;
                    $subtotal += $lineTotal;

                    OrderItem::factory()->create([
                        'order_id' => $order->id,
                        'variant_external_id' => $variant->external_id,
                        'sku' => $variant->sku,
                        'name_snapshot' => $variant->product?->name ?? fake()->words(3, true),
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]);
                }

                $discount = fake()->boolean(25) ? fake()->numberBetween(0, (int) floor($subtotal * 0.15)) : 0;
                $shipping = fake()->numberBetween(0, 2500);
                $tax = (int) floor(max(0, $subtotal - $discount) * 0.12);

                $order->update([
                    'subtotal' => $subtotal,
                    'discount_total' => $discount,
                    'shipping_total' => $shipping,
                    'tax_total' => $tax,
                    'grand_total' => max(0, $subtotal - $discount + $shipping + $tax),
                ]);
            });
    }

    private function seedSyncRuns(): void
    {
        SyncRun::factory()
            ->count(6)
            ->completed()
            ->create();

        SyncRun::factory()
            ->count(3)
            ->failed()
            ->create();

        SyncRun::factory()
            ->count(1)
            ->running()
            ->create();
    }
}
