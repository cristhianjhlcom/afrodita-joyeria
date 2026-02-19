<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders product variants and images in admin product detail page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $product = Product::factory()->create([
        'name' => 'Ariel Ring',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'ARIEL-001',
        'size' => 'M',
        'color' => 'Gold',
        'stock_available' => 8,
    ]);

    ProductImage::factory()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'url' => 'https://cdn.example.com/ariel-ring-main.jpg',
        'is_primary' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products.show', $product))
        ->assertSuccessful()
        ->assertSee('Ariel Ring')
        ->assertSee('ARIEL-001')
        ->assertSee('https://cdn.example.com/ariel-ring-main.jpg');
});

it('shows view action from products listing', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.products'))
        ->assertSuccessful()
        ->assertSee(route('admin.products.show', $product));
});
