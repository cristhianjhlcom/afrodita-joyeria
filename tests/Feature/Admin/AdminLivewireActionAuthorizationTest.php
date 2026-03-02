<?php

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('blocks customers from queueing products sync actions directly via livewire', function () {
    $customer = User::factory()->create([
        'role' => UserRole::Customer,
    ]);

    Artisan::spy();

    Livewire::actingAs($customer)
        ->test('pages::admin.products')
        ->call('queueProductsSync')
        ->assertForbidden();

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

it('blocks customers from creating brand integrations directly via livewire', function () {
    $customer = User::factory()->create([
        'role' => UserRole::Customer,
    ]);

    Livewire::actingAs($customer)
        ->test('pages::admin.brands')
        ->set('newBrandName', 'Afrodita')
        ->set('newBrandExternalId', 22001)
        ->set('newBrandToken', '1|blocked-token')
        ->call('createBrandIntegration');

    expect(Brand::query()->where('external_id', 22001)->exists())->toBeFalse();
});
