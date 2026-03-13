<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the storefront cart page route', function () {
    $this->get(route('storefront.cart.show'))
        ->assertSuccessful()
        ->assertSee('Cart');
});

it('renders cart items from session on cart page', function () {
    $product = Product::factory()->create([
        'name' => 'Cart Session Product',
        'slug' => 'cart-session-product',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'M',
        'color' => 'Dorado',
        'price' => 10900,
        'stock_available' => 6,
        'is_active' => true,
    ]);

    session([CartService::SESSION_KEY => [$variant->id => 2]]);

    $this->get(route('storefront.cart.show'))
        ->assertSuccessful()
        ->assertSee('Cart Session Product')
        ->assertSee('2');
});

it('updates quantities and removes items from cart page actions', function () {
    $product = Product::factory()->create([
        'name' => 'Cart Actions Product',
        'slug' => 'cart-actions-product',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 10900,
        'stock_available' => 2,
        'is_active' => true,
    ]);

    session([CartService::SESSION_KEY => [$variant->id => 1]]);

    Livewire::test('pages::storefront.cart')
        ->call('increase', $variant->id)
        ->assertSet('feedbackSuccess', true)
        ->call('increase', $variant->id)
        ->assertSet('feedbackSuccess', false)
        ->call('decrease', $variant->id)
        ->assertSet('feedbackSuccess', true)
        ->call('removeItem', $variant->id)
        ->assertSet('feedbackSuccess', true);

    expect(session(CartService::SESSION_KEY, []))->toBe([]);
});

it('shows checkout action from cart page', function () {
    $product = Product::factory()->create([
        'name' => 'Cart Checkout Product',
        'slug' => 'cart-checkout-product',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'external_id' => 554422,
        'sku' => 'AFR-CHK-01',
        'price' => 20000,
        'stock_available' => 3,
        'is_active' => true,
    ]);

    session([CartService::SESSION_KEY => [$variant->id => 2]]);

    $this->get(route('storefront.cart.show'))
        ->assertSuccessful()
        ->assertSee(route('storefront.checkout.show'))
        ->assertSee(__('Go to checkout'));
});
