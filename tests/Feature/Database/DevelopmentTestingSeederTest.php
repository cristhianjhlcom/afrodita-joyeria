<?php

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SyncRun;
use App\Models\User;
use Database\Seeders\DevelopmentTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds development testing data for local api-less workflows', function () {
    $this->seed(DevelopmentTestingSeeder::class);

    expect(User::query()->where('email', 'dev-admin@afrodita.local')->where('role', UserRole::Admin)->exists())->toBeTrue();
    expect(Brand::query()->count())->toBe(8);
    expect(BrandWhitelist::query()->count())->toBe(8);
    expect(BrandWhitelist::query()->where('enabled', true)->count())->toBe(6);
    expect(Product::query()->count())->toBeGreaterThan(0);
    expect(ProductVariant::query()->count())->toBeGreaterThan(0);
    expect(ProductImage::query()->count())->toBeGreaterThan(0);
    expect(Order::query()->count())->toBe(40);
    expect(OrderItem::query()->count())->toBeGreaterThan(0);
    expect(SyncRun::query()->count())->toBe(10);
});
