<?php

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows admin to toggle brand whitelist flag', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $brand = Brand::factory()->create();
    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.brands')
        ->call('toggleWhitelist', $brand->id);

    expect(BrandWhitelist::query()->where('brand_id', $brand->id)->firstOrFail()->enabled)->toBeTrue();

    Livewire::actingAs($admin)
        ->test('pages::admin.brands')
        ->call('toggleWhitelist', $brand->id);

    expect(BrandWhitelist::query()->where('brand_id', $brand->id)->firstOrFail()->enabled)->toBeFalse();
});
