<?php

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('filters brands by search query', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Brand::factory()->create(['name' => 'Aurora Collection']);
    Brand::factory()->create(['name' => 'Nebula Gems']);

    Livewire::actingAs($admin)
        ->test('pages::admin.brands')
        ->set('search', 'Aurora')
        ->assertSee('Aurora Collection')
        ->assertDontSee('Nebula Gems');
});

it('paginates brands list', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    foreach (range(1, 13) as $index) {
        Brand::factory()->create([
            'name' => sprintf('Brand %02d', $index),
        ]);
    }

    Livewire::actingAs($admin)
        ->test('pages::admin.brands')
        ->assertSee('Brand 01')
        ->assertDontSee('Brand 13')
        ->call('setPage', 2)
        ->assertSee('Brand 13')
        ->assertDontSee('Brand 01');
});

it('filters products by search, brand and category', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $matchingBrand = Brand::factory()->create(['name' => 'Golden Arc']);
    $otherBrand = Brand::factory()->create(['name' => 'Silver Arc']);

    $parent = Category::factory()->create(['name' => 'Rings']);
    $matchingSubcategory = Category::factory()->subcategory($parent)->create(['name' => 'Engagement Rings']);
    $otherSubcategory = Category::factory()->subcategory($parent)->create(['name' => 'Wedding Rings']);

    Product::factory()->create([
        'name' => 'Celeste Diamond Ring',
        'brand_id' => $matchingBrand->id,
        'subcategory_id' => $matchingSubcategory->id,
    ]);

    Product::factory()->create([
        'name' => 'Luna Silver Ring',
        'brand_id' => $otherBrand->id,
        'subcategory_id' => $otherSubcategory->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.products')
        ->set('search', 'Celeste')
        ->set('brand', (string) $matchingBrand->id)
        ->set('category', (string) $matchingSubcategory->id)
        ->assertSee('Celeste Diamond Ring')
        ->assertDontSee('Luna Silver Ring');
});

it('filters inventory by low stock toggle', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $product = Product::factory()->create([
        'name' => 'Inventory Test Product',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'INV-LOW-001',
        'stock_available' => 3,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'INV-HIGH-001',
        'stock_available' => 12,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.inventory')
        ->set('onlyLowStock', true)
        ->assertSee('INV-LOW-001')
        ->assertDontSee('INV-HIGH-001');
});

it('applies inventory search and low stock filters together', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $matchingProduct = Product::factory()->create([
        'name' => 'Aurora Bracelet',
    ]);

    $otherProduct = Product::factory()->create([
        'name' => 'Luna Necklace',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $matchingProduct->id,
        'sku' => 'AURORA-LOW-01',
        'stock_available' => 2,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $otherProduct->id,
        'sku' => 'LUNA-HIGH-01',
        'stock_available' => 20,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.inventory')
        ->set('search', 'Luna')
        ->set('onlyLowStock', true)
        ->assertDontSee('AURORA-LOW-01')
        ->assertDontSee('LUNA-HIGH-01');
});

it('filters and paginates mirrored orders list', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    foreach (range(1, 13) as $index) {
        Order::factory()->create([
            'external_id' => 500000 + $index,
            'placed_at' => now()->subMinutes($index),
        ]);
    }

    Livewire::actingAs($admin)
        ->test('pages::admin.orders')
        ->set('search', '500013')
        ->assertSee('500013')
        ->assertDontSee('500001');

    Livewire::actingAs($admin)
        ->test('pages::admin.orders')
        ->assertSee('500001')
        ->assertDontSee('500013')
        ->call('setPage', 2)
        ->assertSee('500013')
        ->assertDontSee('500001');
});
