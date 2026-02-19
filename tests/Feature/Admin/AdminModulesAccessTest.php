<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admins to access admin modules', function (string $routeName) {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertSuccessful();
})->with([
    'admin dashboard' => 'admin.dashboard',
    'admin brands' => 'admin.brands',
    'admin categories' => 'admin.categories',
    'admin products' => 'admin.products',
    'admin inventory' => 'admin.inventory',
    'admin orders' => 'admin.orders',
]);

it('allows admins to access product detail module', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.products.show', $product))
        ->assertSuccessful();
});

it('forbids customers from admin modules', function (string $routeName) {
    $customer = User::factory()->create([
        'role' => UserRole::Customer,
    ]);

    $this->actingAs($customer)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'admin.dashboard',
    'admin.brands',
    'admin.categories',
    'admin.products',
    'admin.inventory',
    'admin.orders',
]);

it('forbids customers from product detail module', function () {
    $customer = User::factory()->create([
        'role' => UserRole::Customer,
    ]);

    $product = Product::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.products.show', $product))
        ->assertForbidden();
});
