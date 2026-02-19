<?php

use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.main_store.base_url', 'https://main-store.test');
    config()->set('services.main_store.token', 'test-token');
});

it('syncs catalog resources from main store', function () {
    Http::fake([
        'https://main-store.test/api/v1/sync/brands*' => Http::response([
            'data' => [
                [
                    'id' => 10,
                    'name' => 'Main Brand',
                    'slug' => 'main-brand',
                    'is_active' => true,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'brands'])
        ->assertSuccessful();

    $brand = Brand::query()->where('external_id', 10)->firstOrFail();

    expect($brand->name)->toBe('Main Brand');
    expect(BrandWhitelist::query()->where('brand_id', $brand->id)->exists())->toBeTrue();

    BrandWhitelist::query()->where('brand_id', $brand->id)->update(['enabled' => true]);

    Http::fake([
        'https://main-store.test/api/v1/sync/categories*' => Http::response([
            'data' => [
                [
                    'id' => 100,
                    'name' => 'Rings',
                    'slug' => 'rings',
                    'is_active' => true,
                    'updated_at' => now()->toIso8601String(),
                ],
                [
                    'id' => 101,
                    'name' => 'Gold Rings',
                    'slug' => 'gold-rings',
                    'parent_id' => 100,
                    'is_active' => true,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/products*' => Http::response([
            'data' => [
                [
                    'id' => 501,
                    'name' => 'Elegant Ring',
                    'slug' => 'elegant-ring',
                    'description' => 'A ring',
                    'status' => 'published',
                    'subcategory_id' => 101,
                    'brand_id' => 10,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/variants*' => Http::response([
            'data' => [
                [
                    'id' => 701,
                    'sku' => 'SKU-0001',
                    'code' => 'VAR-0001',
                    'price' => 19900,
                    'sale_price' => 14900,
                    'color' => 'Gold',
                    'hex' => '#FFD700',
                    'size' => '7',
                    'product_id' => 501,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/inventory*' => Http::response([
            'data' => [
                [
                    'variant_id' => 701,
                    'stock_on_hand' => 20,
                    'stock_reserved' => 3,
                    'stock_available' => 17,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/variant-images*' => Http::response([
            'data' => [
                [
                    'id' => 1001,
                    'product_id' => 501,
                    'variant_id' => 701,
                    'url' => 'https://cdn.main-store.test/products/501-1.jpg',
                    'sort_order' => 1,
                    'is_primary' => true,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'categories'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'variants'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'images'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'inventory'])->assertSuccessful();

    expect(Product::query()->where('external_id', 501)->exists())->toBeTrue();
    expect(ProductVariant::query()->where('external_id', 701)->where('stock_available', 17)->exists())->toBeTrue();
});

it('syncs mirrored orders from main store', function () {
    Http::fake([
        'https://main-store.test/api/v1/sync/orders*' => Http::response([
            'data' => [
                [
                    'id' => 9001,
                    'customer_id' => 321,
                    'status' => 'paid',
                    'currency' => 'USD',
                    'subtotal' => 10000,
                    'discount_total' => 0,
                    'shipping_total' => 0,
                    'tax_total' => 0,
                    'grand_total' => 10000,
                    'placed_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                    'items' => [
                        [
                            'variant_id' => 701,
                            'sku' => 'SKU-0001',
                            'name_snapshot' => 'Elegant Ring',
                            'qty' => 1,
                            'unit_price' => 10000,
                            'line_total' => 10000,
                        ],
                    ],
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'orders'])
        ->assertSuccessful();

    $order = Order::query()->where('external_id', 9001)->firstOrFail();

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->sku)->toBe('SKU-0001');
});
