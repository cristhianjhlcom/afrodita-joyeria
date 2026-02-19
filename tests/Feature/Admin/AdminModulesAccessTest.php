<?php

use App\Enums\UserRole;
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
