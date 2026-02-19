<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admins to access admin dashboard', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful();
});

it('forbids customers from accessing admin dashboard', function () {
    $customer = User::factory()->create([
        'role' => UserRole::Customer,
    ]);

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('redirects guests from admin dashboard to login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});
