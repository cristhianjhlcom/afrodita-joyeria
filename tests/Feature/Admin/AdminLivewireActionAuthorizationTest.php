<?php

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('blocks customers from queueing resource sync actions directly via livewire', function () {
    $customer = User::factory()->create([
        'role' => UserRole::Customer,
    ]);

    Artisan::spy();

    Livewire::actingAs($customer)
        ->test('pages::admin.dashboard')
        ->call('queueResourceSync', 'products')
        ->assertSet('queuedResource', null);

    Artisan::shouldNotHaveReceived('call');
});

it('blocks customers from toggling whitelist directly via livewire', function () {
    $customer = User::factory()->create([
        'role' => UserRole::Customer,
    ]);

    $brand = Brand::factory()->create();

    BrandWhitelist::factory()->create([
        'brand_id' => $brand->id,
        'enabled' => false,
    ]);

    Livewire::actingAs($customer)
        ->test('pages::admin.brands')
        ->call('toggleWhitelist', $brand->id);

    expect(BrandWhitelist::query()->where('brand_id', $brand->id)->firstOrFail()->enabled)->toBeFalse();
});
