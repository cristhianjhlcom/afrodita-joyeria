<?php

use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\Category;
use App\Models\Country;
use App\Models\Department;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\SyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.main_store.base_url', 'https://main-store.test');
    config()->set('services.main_store.token', 'test-token');
});

it('fails early when main store base url is missing', function () {
    config()->set('services.main_store.base_url', '');

    $this->artisan('main-store:sync', ['resource' => 'all', '--queued' => true])
        ->assertFailed();
});

it('skips updated_since when full sync is requested', function () {
    SyncRun::factory()->completed()->create([
        'resource' => 'brands',
        'checkpoint_updated_since' => now()->subDay(),
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/brand*' => Http::response([
            'data' => [],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'brands', '--full' => true])
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://main-store.test/api/v1/sync/brand?per_page=100'
            && ! str_contains($request->url(), 'updated_since=');
    });
});

it('fails early when no token exists in enabled brand integrations and fallback token is missing', function () {
    config()->set('services.main_store.token', '');
    BrandWhitelist::query()->delete();

    $this->artisan('main-store:sync', ['resource' => 'brands'])
        ->assertFailed();
});

it('syncs catalog resources from main store', function () {
    Http::fake([
        'https://main-store.test/api/v1/sync/brand?*' => Http::response([], 404),
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
        'https://main-store.test/api/v1/sync/subcategories?*' => Http::response([], 404),
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
                    'include_in_merchant' => true,
                    'gtin' => '1234567890123',
                    'mpn' => 'MPN-001',
                    'google_product_category' => 'Jewelry',
                    'sale_price_starts_at' => now()->subDay()->toIso8601String(),
                    'sale_price_ends_at' => now()->addDay()->toIso8601String(),
                    'product_id' => 501,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/stocks?*' => Http::response([], 404),
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
                    'id' => 'variant-701-image',
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

    $variant = ProductVariant::query()->where('external_id', 701)->firstOrFail();

    expect($variant->stock_available)->toBe(17);
    expect($variant->include_in_merchant)->toBeTrue();
    expect($variant->gtin)->toBe('1234567890123');
    expect($variant->mpn)->toBe('MPN-001');
    expect($variant->google_product_category)->toBe('Jewelry');
    expect($variant->sale_price_starts_at)->not->toBeNull();
    expect($variant->sale_price_ends_at)->not->toBeNull();
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
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/order-items*' => Http::response([
            'data' => [
                [
                    'id' => 7000,
                    'order_id' => 9001,
                    'variant_id' => 701,
                    'sku' => 'SKU-0001',
                    'name_snapshot' => 'Elegant Ring',
                    'qty' => 1,
                    'unit_price' => 10000,
                    'line_total' => 10000,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'orders'])
        ->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'order-items'])
        ->assertSuccessful();

    $order = Order::query()->where('external_id', 9001)->firstOrFail();

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->sku)->toBe('SKU-0001');
    expect($order->items->first()->external_id)->toBe(7000);
    expect($order->getRawOriginal('updated_at'))->toContain(' ');
    expect($order->getRawOriginal('updated_at'))->not->toContain('T');
});

it('syncs address resources from main store endpoints', function () {
    Http::fake([
        'https://main-store.test/api/v1/sync/countries*' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Perú',
                    'iso_code_2' => 'PE',
                    'iso_code_3' => 'PER',
                    'is_active' => true,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/departments*' => Http::response([
            'data' => [
                [
                    'id' => 15,
                    'country_id' => 1,
                    'name' => 'Lima',
                    'ubigeo_code' => '15',
                    'country' => [
                        'id' => 1,
                        'name' => 'Perú',
                        'iso_code_2' => 'PE',
                        'iso_code_3' => 'PER',
                    ],
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/provinces*' => Http::response([
            'data' => [
                [
                    'id' => 1501,
                    'department_id' => 15,
                    'name' => 'Lima',
                    'ubigeo_code' => '1501',
                    'shipping_price' => '12.50',
                    'cost' => '10.00',
                    'is_active' => true,
                    'country' => [
                        'id' => 1,
                        'name' => 'Perú',
                        'iso_code_2' => 'PE',
                        'iso_code_3' => 'PER',
                    ],
                    'department' => [
                        'id' => 15,
                        'country_id' => 1,
                        'name' => 'Lima',
                        'ubigeo_code' => '15',
                    ],
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/districts*' => Http::response([
            'data' => [
                [
                    'id' => 150101,
                    'province_id' => 1501,
                    'name' => 'Lima',
                    'ubigeo_code' => '150101',
                    'shipping_price' => '12.50',
                    'has_delivery_express' => true,
                    'is_active' => true,
                    'country' => [
                        'id' => 1,
                        'name' => 'Perú',
                        'iso_code_2' => 'PE',
                        'iso_code_3' => 'PER',
                    ],
                    'department' => [
                        'id' => 15,
                        'country_id' => 1,
                        'name' => 'Lima',
                        'ubigeo_code' => '15',
                    ],
                    'province' => [
                        'id' => 1501,
                        'department_id' => 15,
                        'name' => 'Lima',
                        'ubigeo_code' => '1501',
                        'shipping_price' => '12.50',
                        'is_active' => true,
                    ],
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/addresses*' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Perú',
                    'iso_code_2' => 'PE',
                    'iso_code_3' => 'PER',
                    'is_active' => true,
                    'departments' => [
                        [
                            'id' => 15,
                            'country_id' => 1,
                            'name' => 'Lima',
                            'ubigeo_code' => '15',
                            'provinces' => [
                                [
                                    'id' => 1501,
                                    'department_id' => 15,
                                    'name' => 'Lima',
                                    'ubigeo_code' => '1501',
                                    'shipping_price' => '12.50',
                                    'cost' => '10.00',
                                    'is_active' => true,
                                    'districts' => [
                                        [
                                            'id' => 150101,
                                            'province_id' => 1501,
                                            'name' => 'Lima',
                                            'ubigeo_code' => '150101',
                                            'shipping_price' => '12.50',
                                            'has_delivery_express' => true,
                                            'is_active' => true,
                                            'updated_at' => now()->toIso8601String(),
                                        ],
                                    ],
                                    'updated_at' => now()->toIso8601String(),
                                ],
                            ],
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ],
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'countries'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'departments'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'provinces'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'districts'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'addresses'])->assertSuccessful();

    expect(Country::query()->where('external_id', 1)->exists())->toBeTrue();
    expect(Department::query()->where('external_id', 15)->exists())->toBeTrue();
    expect(Province::query()->where('external_id', 1501)->where('shipping_price', 1250)->where('cost', 1000)->exists())->toBeTrue();
    expect(District::query()->where('external_id', 150101)->where('shipping_price', 1250)->where('has_delivery_express', true)->exists())->toBeTrue();
});

it('supports endpoint aliases for brand and stock sync resources', function () {
    Http::fake([
        'https://main-store.test/api/v1/sync/brand*' => Http::response([
            'data' => [
                'id' => 11,
                'name' => 'Alias Brand',
                'slug' => 'alias-brand',
                'is_active' => true,
                'updated_at' => now()->toIso8601String(),
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/stocks*' => Http::response([
            'data' => [
                [
                    'variant_id' => 702,
                    'stock_on_hand' => 9,
                    'stock_reserved' => 2,
                    'stock_available' => 7,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    Product::factory()->create([
        'external_id' => 601,
    ]);

    ProductVariant::factory()->create([
        'external_id' => 702,
        'product_id' => Product::query()->firstOrFail()->id,
    ]);

    $this->artisan('main-store:sync', ['resource' => 'brands'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'inventory'])->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str($request->url())->contains('/api/v1/sync/brand'));
    Http::assertSent(fn (Request $request): bool => str($request->url())->contains('/api/v1/sync/stocks'));

    expect(Brand::query()->where('external_id', 11)->exists())->toBeTrue();
    expect(ProductVariant::query()->where('external_id', 702)->where('stock_available', 7)->exists())->toBeTrue();
});

it('does not soft delete products when sync returns empty payload', function () {
    $brand = Brand::factory()->create([
        'external_id' => 55,
    ]);

    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => true,
        'main_store_token' => '1|brand-token',
    ]);

    $product = Product::factory()->create([
        'external_id' => 9090,
        'brand_id' => $brand->id,
        'deleted_at' => null,
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/products*' => Http::response([
            'data' => [],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();

    $product->refresh();

    expect($product->deleted_at)->toBeNull();
});

it('retries inventory sync when updated_since format causes server error', function () {
    $product = Product::factory()->create([
        'external_id' => 801,
    ]);

    $variant = ProductVariant::factory()->create([
        'external_id' => 802,
        'product_id' => $product->id,
        'stock_available' => 0,
    ]);

    SyncRun::factory()->completed()->create([
        'resource' => 'inventory',
        'checkpoint_updated_since' => now()->subDay(),
    ]);

    Http::fakeSequence('https://main-store.test/api/v1/sync/stocks*')
        ->push([
            'error' => [
                'code' => 'server_error',
                'message' => 'Illegal operator and value combination.',
            ],
        ], 500)
        ->push([
            'data' => [
                [
                    'variant_id' => 802,
                    'stock_on_hand' => 4,
                    'stock_reserved' => 1,
                    'stock_available' => 3,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ], 200);

    $this->artisan('main-store:sync', ['resource' => 'inventory'])->assertSuccessful();

    $variant->refresh();

    expect($variant->stock_available)->toBe(3);
    Http::assertSentCount(2);
});

it('does not fail image sync when variant-images endpoint is missing', function () {
    Http::fake([
        'https://main-store.test/api/v1/sync/variant-images*' => Http::response([], 404),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'images'])->assertSuccessful();
});

it('syncs subcategories endpoint into category parent relations', function () {
    Http::fake([
        'https://main-store.test/api/v1/sync/categories*' => Http::response([
            'data' => [
                [
                    'id' => 300,
                    'name' => 'Bracelets',
                    'slug' => 'bracelets',
                    'is_active' => true,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
        'https://main-store.test/api/v1/sync/subcategories*' => Http::response([
            'data' => [
                [
                    'id' => 301,
                    'category_id' => 300,
                    'name' => 'Charm Bracelets',
                    'slug' => 'charm-bracelets',
                    'is_active' => true,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'categories'])->assertSuccessful();

    $parent = Category::query()->where('external_id', 300)->firstOrFail();
    $child = Category::query()->where('external_id', 301)->firstOrFail();

    expect($child->parent_id)->toBe($parent->id);
});

it('uses brand integration token when syncing resources', function () {
    config()->set('services.main_store.token', 'global-fallback-token');

    $brand = Brand::factory()->create([
        'external_id' => 10,
    ]);

    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => true,
        'main_store_token' => '1|brand-integration-token',
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/products*' => Http::response([
            'data' => [],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://main-store.test/api/v1/sync/products?per_page=100'
            && $request->hasHeader('Authorization', 'Bearer 1|brand-integration-token');
    });
});

it('syncs products from nested external payload without numeric ids', function () {
    $brand = Brand::factory()->create([
        'external_id' => 6,
        'name' => 'Afrodita',
        'slug' => 'afrodita',
    ]);

    $category = Category::factory()->create([
        'external_id' => 100,
        'name' => 'Anillos',
        'slug' => 'anillos',
        'parent_id' => null,
    ]);

    Category::factory()->create([
        'external_id' => 101,
        'name' => 'Anillos con Figuras',
        'slug' => 'anillos-con-figuras',
        'parent_id' => $category->id,
    ]);

    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => true,
        'main_store_token' => '1|afrodita-token',
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/products*' => Http::response([
            'data' => [
                [
                    'id' => 9502,
                    'name' => 'Prueba 02',
                    'slug' => 'producto-de-prueba-02',
                    'description' => '<p>Demo</p>',
                    'status' => 'draft',
                    'images' => [
                        'http://localhost:8000/storage/public/products/p-1.webp',
                        'http://localhost:8000/storage/public/products/p-2.webp',
                    ],
                    'brand' => [
                        'name' => 'AFRODITA',
                    ],
                    'category' => [
                        'name' => 'Anillos',
                    ],
                    'subcategory' => [
                        'name' => 'ANILLOS CON FIGURAS',
                    ],
                    'variants' => [
                        [
                            'id' => 9703,
                            'sku' => 'TEST003',
                            'price' => 2990,
                            'sale_price' => 16999,
                            'color' => 'Rubi',
                            'hex' => '#e0115f',
                            'size' => '8',
                            'images' => [
                                'http://localhost:8000/storage/public/variants/v-1.webp',
                            ],
                            'stock' => 15,
                            'in_stock' => true,
                        ],
                    ],
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();

    $product = Product::query()->where('slug', 'producto-de-prueba-02')->firstOrFail();
    $variant = ProductVariant::query()->where('sku', 'TEST003')->firstOrFail();

    expect($product->brand_id)->toBe($brand->id);
    expect($variant->product_id)->toBe($product->id);
    expect($variant->stock_available)->toBe(15);
    expect(ProductImage::query()->where('product_id', $product->id)->count())->toBe(3);
    expect(ProductImage::query()->where('variant_id', $variant->id)->exists())->toBeTrue();
});

it('stores payload order url and normalized product and variant images', function () {
    $brand = Brand::factory()->create([
        'name' => 'Afrodita',
        'slug' => 'afrodita',
    ]);

    $category = Category::factory()->create([
        'name' => 'Anillos',
        'slug' => 'anillos',
    ]);

    Category::factory()->create([
        'name' => 'Anillos con Figuras',
        'slug' => 'anillos-con-figuras',
        'parent_id' => $category->id,
    ]);

    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => true,
        'main_store_token' => '1|afrodita-token',
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/products*' => Http::response([
            'data' => [[
                'id' => 9602,
                'name' => 'Prueba 02',
                'slug' => 'producto-de-prueba-02',
                'description' => '<p>Demo</p>',
                'status' => 'draft',
                'order' => 7,
                'url' => 'http://localhost:8000/anillos/anillos-con-figuras/producto-de-prueba-02',
                'featured_image' => 'http://localhost:8000/storage/public/products/featured-p-1.webp',
                'youtube_video_id' => 'dQw4w9WgXcQ',
                'images' => [
                    'http://localhost:8000/storage/public/products/p-1.webp',
                    'http://localhost:8000/storage/public/products/p-2.webp',
                ],
                'brand' => ['name' => 'Afrodita'],
                'category' => ['name' => 'Anillos'],
                'subcategory' => ['name' => 'Anillos con Figuras'],
                'variants' => [[
                    'id' => 9803,
                    'sku' => 'TEST003',
                    'price' => 2990,
                    'sale_price' => 16999,
                    'color' => 'Rubi',
                    'hex' => '#e0115f',
                    'size' => '8',
                    'image' => 'http://localhost:8000/storage/public/variants/v-1.webp',
                    'images' => [
                        'http://localhost:8000/storage/public/variants/v-1.webp',
                        'http://localhost:8000/storage/public/products/p-1.webp',
                    ],
                    'stock' => 15,
                    'in_stock' => true,
                ]],
                'updated_at' => now()->toIso8601String(),
            ]],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();

    $product = Product::query()->where('slug', 'producto-de-prueba-02')->firstOrFail();
    $variant = ProductVariant::query()->where('sku', 'TEST003')->firstOrFail();

    expect($product->sort_order)->toBe(7);
    expect($product->url)->toContain('/producto-de-prueba-02');
    expect($product->featured_image)->toContain('/products/featured-p-1.webp');
    expect($product->youtube_video_id)->toBe('dQw4w9WgXcQ');
    expect($variant->primary_image_url)->toContain('/variants/v-1.webp');
    expect(ProductImage::query()->where('product_id', $product->id)->count())->toBe(4);
    expect(ProductImage::query()->where('variant_id', $variant->id)->exists())->toBeTrue();
});

it('keeps products when a full sync payload is empty', function () {
    $brand = Brand::factory()->create([
        'name' => 'Afrodita',
        'slug' => 'afrodita',
    ]);

    $category = Category::factory()->create([
        'name' => 'Anillos',
        'slug' => 'anillos',
    ]);

    Category::factory()->create([
        'name' => 'Anillos con Figuras',
        'slug' => 'anillos-con-figuras',
        'parent_id' => $category->id,
    ]);

    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => true,
        'main_store_token' => '1|afrodita-token',
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/products*' => Http::sequence()
            ->push([
                'data' => [[
                    'id' => 9901,
                    'name' => 'Producto Activo',
                    'slug' => 'producto-activo',
                    'status' => 'published',
                    'brand' => ['name' => 'Afrodita'],
                    'category' => ['name' => 'Anillos'],
                    'subcategory' => ['name' => 'Anillos con Figuras'],
                    'variants' => [],
                    'updated_at' => now()->toIso8601String(),
                ]],
                'meta' => ['next_cursor' => null],
            ])
            ->push([
                'data' => [],
                'meta' => ['next_cursor' => null],
            ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();
    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();

    expect(Product::query()->withTrashed()->where('slug', 'producto-activo')->firstOrFail()->trashed())->toBeFalse();
});

it('does not duplicate subcategory creation when same slug appears in one products payload', function () {
    $brand = Brand::factory()->create([
        'name' => 'Afrodita',
        'slug' => 'afrodita',
    ]);

    Category::factory()->create([
        'name' => 'Accesorios',
        'slug' => 'accesorios',
    ]);

    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => true,
        'main_store_token' => '1|afrodita-token',
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/products*' => Http::response([
            'data' => [
                [
                    'id' => 9101,
                    'name' => 'P1',
                    'slug' => 'p1',
                    'status' => 'published',
                    'brand' => ['name' => 'Afrodita'],
                    'category' => ['name' => 'Accesorios'],
                    'subcategory' => ['name' => 'Accesorios para el Cabello'],
                    'variants' => [],
                    'updated_at' => now()->toIso8601String(),
                ],
                [
                    'id' => 9102,
                    'name' => 'P2',
                    'slug' => 'p2',
                    'status' => 'published',
                    'brand' => ['name' => 'Afrodita'],
                    'category' => ['name' => 'Accesorios'],
                    'subcategory' => ['name' => 'Accesorios para el Cabello'],
                    'variants' => [],
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();

    expect(Category::query()->where('slug', 'accesorios-para-el-cabello')->count())->toBe(1);
});

it('does not soft delete products from earlier pages in the same sync run', function () {
    $brand = Brand::factory()->create([
        'name' => 'Afrodita',
        'slug' => 'afrodita',
    ]);

    $category = Category::factory()->create([
        'name' => 'Anillos',
        'slug' => 'anillos',
    ]);

    Category::factory()->create([
        'name' => 'Anillos con Figuras',
        'slug' => 'anillos-con-figuras',
        'parent_id' => $category->id,
    ]);

    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => true,
        'main_store_token' => '1|afrodita-token',
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/products*' => Http::sequence()
            ->push([
                'data' => [
                    [
                        'id' => 9201,
                        'name' => 'P1',
                        'slug' => 'p1',
                        'status' => 'published',
                        'brand' => ['name' => 'Afrodita'],
                        'category' => ['name' => 'Anillos'],
                        'subcategory' => ['name' => 'Anillos con Figuras'],
                        'variants' => [],
                        'updated_at' => now()->toIso8601String(),
                    ],
                    [
                        'id' => 9202,
                        'name' => 'P2',
                        'slug' => 'p2',
                        'status' => 'published',
                        'brand' => ['name' => 'Afrodita'],
                        'category' => ['name' => 'Anillos'],
                        'subcategory' => ['name' => 'Anillos con Figuras'],
                        'variants' => [],
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
                'meta' => ['next_cursor' => 'cursor-2'],
            ])
            ->push([
                'data' => [
                    [
                        'id' => 9203,
                        'name' => 'P3',
                        'slug' => 'p3',
                        'status' => 'published',
                        'brand' => ['name' => 'Afrodita'],
                        'category' => ['name' => 'Anillos'],
                        'subcategory' => ['name' => 'Anillos con Figuras'],
                        'variants' => [],
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
                'meta' => ['next_cursor' => null],
            ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();

    expect(Product::query()->count())->toBe(3);
    expect(Product::query()->whereNotNull('deleted_at')->count())->toBe(0);
});

it('syncs products and nested variants even when ids are missing in products payload', function () {
    $brand = Brand::factory()->create([
        'external_id' => 10,
        'name' => 'Afrodita',
        'slug' => 'afrodita',
    ]);

    $category = Category::factory()->create([
        'external_id' => 200,
        'name' => 'Accesorios',
        'slug' => 'accesorios',
    ]);

    $subcategory = Category::factory()->create([
        'external_id' => 201,
        'name' => 'Accesorios para el Cabello',
        'slug' => 'accesorios-para-el-cabello',
        'parent_id' => $category->id,
    ]);

    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => true,
        'main_store_token' => '1|afrodita-token',
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/products*' => Http::response([
            'data' => [[
                'name' => 'Producto Sin ID',
                'slug' => 'producto-sin-id',
                'status' => 'published',
                'brand' => ['name' => 'Afrodita'],
                'category' => ['name' => 'Accesorios'],
                'subcategory' => ['name' => 'Accesorios para el Cabello'],
                'variants' => [[
                    'sku' => 'NO-ID-SKU',
                    'price' => 2990,
                    'sale_price' => 1990,
                    'color' => 'Negro',
                    'size' => 'T/U',
                    'stock' => 12,
                    'in_stock' => true,
                ]],
                'updated_at' => now()->toIso8601String(),
            ]],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'products'])->assertSuccessful();

    $product = Product::query()->where('slug', 'producto-sin-id')->firstOrFail();
    $variant = ProductVariant::query()->where('sku', 'NO-ID-SKU')->firstOrFail();

    expect($product->brand_id)->toBe($brand->id);
    expect($product->subcategory_id)->toBe($subcategory->id);
    expect($variant->product_id)->toBe($product->id);
    expect($product->external_id)->toBeInt()->toBeGreaterThan(0);
    expect($variant->external_id)->toBeInt()->toBeGreaterThan(0);
});

it('syncs inventory from stocks amount entries by aggregating totals per variant', function () {
    $product = Product::factory()->create([
        'external_id' => 501,
    ]);

    $variant = ProductVariant::factory()->create([
        'external_id' => 701,
        'product_id' => $product->id,
        'stock_on_hand' => 0,
        'stock_reserved' => 0,
        'stock_available' => 0,
    ]);

    Http::fake([
        'https://main-store.test/api/v1/sync/stocks*' => Http::response([
            'data' => [
                ['variant_id' => 701, 'amount' => 5, 'updated_at' => now()->subMinute()->toIso8601String()],
                ['variant_id' => 701, 'amount' => -2, 'updated_at' => now()->toIso8601String()],
            ],
            'meta' => ['next_cursor' => null],
        ]),
    ]);

    $this->artisan('main-store:sync', ['resource' => 'inventory'])->assertSuccessful();

    $variant->refresh();

    expect($variant->stock_on_hand)->toBe(3);
    expect($variant->stock_reserved)->toBe(0);
    expect($variant->stock_available)->toBe(3);
});
