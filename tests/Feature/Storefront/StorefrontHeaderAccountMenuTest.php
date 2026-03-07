<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows account dropdown links for authenticated users in storefront header', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertSuccessful()
        ->assertSee('Mi cuenta')
        ->assertSee(route('settings.orders'))
        ->assertSee(route('profile.edit'))
        ->assertSee(route('user-password.edit'))
        ->assertSee(route('appearance.edit'))
        ->assertSee(route('logout'));
});
