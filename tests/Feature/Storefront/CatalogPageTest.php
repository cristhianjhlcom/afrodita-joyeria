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

it('filters catalog by color and size', function () {
    $subcategory = Category::factory()->create();

    $goldRing = Product::factory()->create([
        'name' => 'Gold Ring',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    $blueRing = Product::factory()->create([
        'name' => 'Blue Ring',
        'subcategory_id' => $subcategory->id,
        'category_id' => null,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $goldRing->id,
        'color' => 'Gold',
        'size' => 'M',
        'price' => 28000,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $blueRing->id,
        'color' => 'Blue',
        'size' => 'L',
        'price' => 18000,
    ]);

    $this->get(route('home', ['colors' => ['Gold'], 'sizes' => ['M']]))
        ->assertSuccessful()
        ->assertSee('Gold Ring')
        ->assertDontSee('Blue Ring');
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

    $this->get(route('home', ['min' => '100', 'max' => '360']))
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
