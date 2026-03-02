<?php

use App\Models\ProductVariant;
use Tests\TestCase;

uses(TestCase::class);

it('formats variant price and sale price using laravel money helper', function () {
    $variant = new ProductVariant([
        'price' => 19900,
        'sale_price' => 14900,
    ]);

    expect($variant->formattedPrice('COP'))->toBe(money(19900, 'COP')->format())
        ->and($variant->formattedSalePrice('COP'))->toBe(money(14900, 'COP')->format());
});

it('returns dash for empty monetary values', function () {
    $variant = new ProductVariant([
        'price' => null,
        'sale_price' => null,
    ]);

    expect($variant->formattedPrice())->toBe('-')
        ->and($variant->formattedSalePrice())->toBe('-');
});
