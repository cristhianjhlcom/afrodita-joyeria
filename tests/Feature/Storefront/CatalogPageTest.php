<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the storefront catalog on home route', function () {
    $subcategory = Category::factory()->create();

    $product = Product::factory()->create([
        'name' => 'Ariel Ring',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 19900,
        'sale_price' => null,
        'is_active' => true,
    ]);

    ProductImage::factory()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'is_primary' => true,
    ]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('Catalog')
        ->assertSee('Ariel Ring');
});

it('shows all products when no search or filters are applied', function () {
    $subcategory = Category::factory()->create();

    $withVariant = Product::factory()->create([
        'name' => 'Variant Product',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    $withoutVariant = Product::factory()->create([
        'name' => 'No Variant Product',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $withVariant->id,
        'price' => 19900,
        'is_active' => true,
    ]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('Variant Product')
        ->assertSee('No Variant Product');
});

it('paginates products on storefront grid', function () {
    $subcategory = Category::factory()->create();

    foreach (range(1, 13) as $index) {
        $product = Product::factory()->create([
            'name' => "Catalog Product {$index}",
            'subcategory_id' => $subcategory->id,
            'category_id' => null,
            'updated_at' => now()->addSeconds($index),
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 10000 + $index,
            'is_active' => true,
        ]);
    }

    $this->get(route('home', ['page' => 2]))
        ->assertSuccessful()
        ->assertSee('Catalog Product 1')
        ->assertDontSee('Catalog Product 13');
});

it('filters catalog by search term', function () {
    $subcategory = Category::factory()->create();

    $matchingProduct = Product::factory()->create([
        'name' => 'Ruby Necklace',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    $otherProduct = Product::factory()->create([
        'name' => 'Silver Bracelet',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    ProductVariant::factory()->create(['product_id' => $matchingProduct->id, 'price' => 25000]);
    ProductVariant::factory()->create(['product_id' => $otherProduct->id, 'price' => 35000]);

    $this->get(route('home', ['q' => 'Ruby']))
        ->assertSuccessful()
        ->assertSee('Ruby Necklace')
        ->assertDontSee('Silver Bracelet');
});

it('filters catalog by min and max price', function () {
    $subcategory = Category::factory()->create();

    $budgetProduct = Product::factory()->create([
        'name' => 'Budget Studs',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    $premiumProduct = Product::factory()->create([
        'name' => 'Premium Diamond Ring',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $budgetProduct->id,
        'price' => 9000,
        'sale_price' => null,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $premiumProduct->id,
        'price' => 40000,
        'sale_price' => 35000,
    ]);

    $this->get(route('home', ['min' => 10000, 'max' => 36000]))
        ->assertSuccessful()
        ->assertSee('Premium Diamond Ring')
        ->assertDontSee('Budget Studs');
});

it('filters catalog by category and subcategory', function () {
    $parentA = Category::factory()->create(['name' => 'Rings', 'slug' => 'rings']);
    $parentB = Category::factory()->create(['name' => 'Necklaces', 'slug' => 'necklaces']);

    $subA = Category::factory()->create([
        'name' => 'Wedding Rings',
        'slug' => 'wedding-rings',
        'parent_id' => $parentA->id,
    ]);

    $subB = Category::factory()->create([
        'name' => 'Pearl Necklaces',
        'slug' => 'pearl-necklaces',
        'parent_id' => $parentB->id,
    ]);

    $ringProduct = Product::factory()->create([
        'name' => 'Wedding Gold Band',
        'category_id' => $parentA->id,
        'subcategory_id' => $subA->id,
    ]);

    $necklaceProduct = Product::factory()->create([
        'name' => 'Pearl Drop Necklace',
        'category_id' => $parentB->id,
        'subcategory_id' => $subB->id,
    ]);

    ProductVariant::factory()->create(['product_id' => $ringProduct->id, 'price' => 22000]);
    ProductVariant::factory()->create(['product_id' => $necklaceProduct->id, 'price' => 33000]);

    $this->get(route('home', ['cats' => [$parentA->id], 'subs' => [$subA->id]]))
        ->assertSuccessful()
        ->assertSee('Wedding Gold Band')
        ->assertDontSee('Pearl Drop Necklace');
});

it('falls back to all products when filters return no results', function () {
    $subcategory = Category::factory()->create();

    $firstProduct = Product::factory()->create([
        'name' => 'Open Catalog Ring',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    $secondProduct = Product::factory()->create([
        'name' => 'Open Catalog Necklace',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    ProductVariant::factory()->create(['product_id' => $firstProduct->id, 'price' => 15000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $secondProduct->id, 'price' => 18000, 'is_active' => true]);

    $this->get(route('home', ['q' => 'non-existent-item']))
        ->assertSuccessful()
        ->assertSee('Open Catalog Ring')
        ->assertSee('Open Catalog Necklace');
});

it('renders product card image from product images and not variant images', function () {
    $subcategory = Category::factory()->create();

    $product = Product::factory()->create([
        'name' => 'Image Source Product',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
        'featured_image' => null,
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 12500,
        'is_active' => true,
    ]);

    ProductImage::factory()->create([
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'url' => 'https://cdn.test/variant-image.png',
        'is_primary' => true,
    ]);

    ProductImage::factory()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'url' => 'https://cdn.test/product-image.png',
        'is_primary' => true,
    ]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('https://cdn.test/product-image.png', false)
        ->assertDontSee('https://cdn.test/variant-image.png', false);
});

it('prioritizes featured image over synced product images in catalog cards', function () {
    $subcategory = Category::factory()->create();

    $product = Product::factory()->create([
        'name' => 'Featured Card Product',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
        'featured_image' => 'https://cdn.test/featured-image.png',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 15000,
        'is_active' => true,
    ]);

    ProductImage::factory()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'url' => 'https://cdn.test/fallback-product-image.png',
        'is_primary' => true,
    ]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('https://cdn.test/featured-image.png', false)
        ->assertDontSee('https://cdn.test/fallback-product-image.png', false);
});
