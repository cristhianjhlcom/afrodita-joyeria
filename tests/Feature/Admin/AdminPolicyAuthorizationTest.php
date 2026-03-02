<?php

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\Department;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('grants policy abilities to admin users', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $brand = Brand::factory()->create();
    $category = Category::factory()->create();
    $country = Country::factory()->create();
    $department = Department::factory()->create();
    $province = Province::factory()->create();
    $district = District::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create();
    $order = Order::factory()->create();
    $syncRun = SyncRun::factory()->create();

    expect($admin->can('viewAny', Brand::class))->toBeTrue();
    expect($admin->can('view', $brand))->toBeTrue();
    expect($admin->can('toggleWhitelist', Brand::class))->toBeTrue();
    expect($admin->can('viewAny', Category::class))->toBeTrue();
    expect($admin->can('view', $category))->toBeTrue();
    expect($admin->can('viewAny', Country::class))->toBeTrue();
    expect($admin->can('view', $country))->toBeTrue();
    expect($admin->can('viewAny', Department::class))->toBeTrue();
    expect($admin->can('view', $department))->toBeTrue();
    expect($admin->can('viewAny', Province::class))->toBeTrue();
    expect($admin->can('view', $province))->toBeTrue();
    expect($admin->can('viewAny', District::class))->toBeTrue();
    expect($admin->can('view', $district))->toBeTrue();
    expect($admin->can('viewAny', Product::class))->toBeTrue();
    expect($admin->can('view', $product))->toBeTrue();
    expect($admin->can('viewAny', ProductVariant::class))->toBeTrue();
    expect($admin->can('view', $variant))->toBeTrue();
    expect($admin->can('viewAny', Order::class))->toBeTrue();
    expect($admin->can('view', $order))->toBeTrue();
    expect($admin->can('viewAny', SyncRun::class))->toBeTrue();
    expect($admin->can('view', $syncRun))->toBeTrue();
    expect($admin->can('trigger', SyncRun::class))->toBeTrue();
});

it('denies policy abilities to customer users', function () {
    $customer = User::factory()->create([
        'role' => UserRole::Customer,
    ]);

    $brand = Brand::factory()->create();
    $category = Category::factory()->create();
    $country = Country::factory()->create();
    $department = Department::factory()->create();
    $province = Province::factory()->create();
    $district = District::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create();
    $order = Order::factory()->create();
    $syncRun = SyncRun::factory()->create();

    expect($customer->can('viewAny', Brand::class))->toBeFalse();
    expect($customer->can('view', $brand))->toBeFalse();
    expect($customer->can('toggleWhitelist', Brand::class))->toBeFalse();
    expect($customer->can('viewAny', Category::class))->toBeFalse();
    expect($customer->can('view', $category))->toBeFalse();
    expect($customer->can('viewAny', Country::class))->toBeFalse();
    expect($customer->can('view', $country))->toBeFalse();
    expect($customer->can('viewAny', Department::class))->toBeFalse();
    expect($customer->can('view', $department))->toBeFalse();
    expect($customer->can('viewAny', Province::class))->toBeFalse();
    expect($customer->can('view', $province))->toBeFalse();
    expect($customer->can('viewAny', District::class))->toBeFalse();
    expect($customer->can('view', $district))->toBeFalse();
    expect($customer->can('viewAny', Product::class))->toBeFalse();
    expect($customer->can('view', $product))->toBeFalse();
    expect($customer->can('viewAny', ProductVariant::class))->toBeFalse();
    expect($customer->can('view', $variant))->toBeFalse();
    expect($customer->can('viewAny', Order::class))->toBeFalse();
    expect($customer->can('view', $order))->toBeFalse();
    expect($customer->can('viewAny', SyncRun::class))->toBeFalse();
    expect($customer->can('view', $syncRun))->toBeFalse();
    expect($customer->can('trigger', SyncRun::class))->toBeFalse();
});
