<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Subcategory;
use App\Services\Storefront\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the storefront product detail page by slug', function () {
    $category = Category::factory()->create(['name' => 'Anillos', 'slug' => 'anillos']);
    $subcategory = Subcategory::factory()->create(['category_id' => $category->id, 'name' => 'Oro', 'slug' => 'oro']);

    $product = Product::factory()->create([
        'name' => 'Anillo Imperial',
        'slug' => 'anillo-imperial',
        'category_id' => $category->id,
        'subcategory_id' => $subcategory->id,
        'description' => 'Pieza premium con acabado artesanal.',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'S',
        'color' => 'Dorado',
        'hex' => '#D4AF37',
        'price' => 19900,
        'stock_available' => 12,
        'is_active' => true,
    ]);

    $this->get(route('storefront.products.show', $product))
        ->assertSuccessful()
        ->assertSee('Anillo Imperial')
        ->assertSee('Talla')
        ->assertSee('Color')
        ->assertSee('Comprar')
        ->assertSee('Agregar al carrito');
});

it('returns 404 for unknown storefront product slug', function () {
    $this->get('/producto/no-existe')
        ->assertNotFound();
});

it('shows color options depending on the selected size', function () {
    $product = Product::factory()->create([
        'name' => 'Anillo Dependiente',
        'slug' => 'anillo-dependiente',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'S',
        'color' => 'Azul',
        'hex' => '#0000FF',
        'stock_available' => 5,
        'is_active' => true,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'M',
        'color' => 'Rojo',
        'hex' => '#FF0000',
        'stock_available' => 0,
        'is_active' => true,
    ]);

    $this->get(route('storefront.products.show', $product))
        ->assertSuccessful()
        ->assertSee('Azul')
        ->assertDontSee('Rojo');
});

it('disables buy and add to cart buttons when selected variant has no stock', function () {
    $product = Product::factory()->create([
        'name' => 'Anillo Agotado',
        'slug' => 'anillo-agotado',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'M',
        'color' => 'Negro',
        'stock_available' => 0,
        'is_active' => true,
    ]);

    $this->get(route('storefront.products.show', $product))
        ->assertSuccessful()
        ->assertSee('Sin stock')
        ->assertSee('aria-disabled="true"', false);
});

it('adds selected in-stock variant to the cart from product detail page', function () {
    $product = Product::factory()->create([
        'name' => 'Anillo Carrito',
        'slug' => 'anillo-carrito',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'M',
        'color' => 'Negro',
        'stock_available' => 3,
        'is_active' => true,
    ]);

    Livewire::test('pages::storefront.product-detail', ['product' => $product])
        ->call('addToCart')
        ->assertSet('cartFeedbackSuccess', true)
        ->assertSet('cartFeedbackMessage', 'Producto agregado al carrito.');

    expect((int) session(CartService::SESSION_KEY.'.'.$variant->id))->toBe(1);
});

it('renders variant and product images together in carousel', function () {
    $product = Product::factory()->create([
        'name' => 'Anillo Carrusel',
        'slug' => 'anillo-carrusel',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'S',
        'color' => 'Plata',
        'stock_available' => 8,
        'is_active' => true,
    ]);

    ProductImage::factory()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'url' => 'https://cdn.test/product-generic.jpg',
        'is_primary' => true,
    ]);

    ProductImage::factory()->create([
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'url' => 'https://cdn.test/variant-priority.jpg',
        'is_primary' => true,
    ]);

    $this->get(route('storefront.products.show', $product))
        ->assertSuccessful()
        ->assertSee('https://cdn.test/variant-priority.jpg', false)
        ->assertSee('https://cdn.test/product-generic.jpg', false);
});

it('renders seo metadata and product schema on detail page', function () {
    $product = Product::factory()->create([
        'name' => 'Anillo SEO',
        'slug' => 'anillo-seo',
        'description' => 'Descripción optimizada para buscadores.',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'S',
        'color' => 'Dorado',
        'stock_available' => 4,
        'price' => 34900,
        'is_active' => true,
    ]);

    $detailUrl = route('storefront.products.show', $product);

    $this->get($detailUrl)
        ->assertSuccessful()
        ->assertSee('<link href="'.$detailUrl.'" rel="canonical">', false)
        ->assertSee('property="og:type"', false)
        ->assertSee('content="product"', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"Product"', false);
});

it('renders product description html as markup', function () {
    $product = Product::factory()->create([
        'name' => 'Anillo HTML',
        'slug' => 'anillo-html',
        'description' => '<p>Texto con <strong>detalle</strong> y <a href="https://example.com">enlace</a>.</p>',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'S',
        'color' => 'Negro',
        'stock_available' => 2,
        'is_active' => true,
    ]);

    $this->get(route('storefront.products.show', $product))
        ->assertSuccessful()
        ->assertSee('<strong>detalle</strong>', false)
        ->assertSee('<a href="https://example.com">enlace</a>', false);
});
