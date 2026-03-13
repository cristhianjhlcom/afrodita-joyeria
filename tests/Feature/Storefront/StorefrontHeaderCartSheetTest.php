<?php

use App\Livewire\Storefront\CartSheet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders cart badge count and view cart action in cart sheet component', function () {
    $product = Product::factory()->create([
        'name' => 'Header Cart Product',
        'slug' => 'header-cart-product',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 14900,
        'stock_available' => 5,
        'is_active' => true,
    ]);

    session([CartService::SESSION_KEY => [$variant->id => 2]]);

    Livewire::test(CartSheet::class)
        ->assertSee('2')
        ->assertSee(route('storefront.cart.show'))
        ->assertSee(route('storefront.checkout.show'));
});

it('updates quantities and clears cart through cart sheet actions', function () {
    $product = Product::factory()->create([
        'name' => 'Header Cart Action Product',
        'slug' => 'header-cart-action-product',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 17900,
        'stock_available' => 2,
        'is_active' => true,
    ]);

    session([CartService::SESSION_KEY => [$variant->id => 1]]);

    Livewire::test(CartSheet::class)
        ->call('increase', $variant->id)
        ->assertSet('feedbackSuccess', true)
        ->call('increase', $variant->id)
        ->assertSet('feedbackSuccess', false)
        ->call('decrease', $variant->id)
        ->assertSet('feedbackSuccess', true)
        ->call('clearCart')
        ->assertSet('feedbackSuccess', true)
        ->assertSet('feedbackMessage', __('Cart cleared.'));

    expect(session(CartService::SESSION_KEY, []))->toBe([]);
});
