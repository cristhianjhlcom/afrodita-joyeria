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
        'main_store_token' => '1|brand-token',
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

it('allows admin to create or update brand integration token', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $brand = Brand::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.brands')
        ->call('openEditIntegrationModal', $brand->id)
        ->set('editingBrandToken', '1|new-brand-token')
        ->call('saveEditedIntegration');

    expect(BrandWhitelist::query()->where('brand_id', $brand->id)->firstOrFail()->main_store_token)
        ->toBe('1|new-brand-token');
});

it('requires token before enabling brand whitelist', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $brand = Brand::factory()->create();
    BrandWhitelist::query()->create([
        'brand_id' => $brand->id,
        'enabled' => false,
        'main_store_token' => null,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.brands')
        ->call('toggleWhitelist', $brand->id)
        ->assertHasErrors(['integration']);

    expect(BrandWhitelist::query()->where('brand_id', $brand->id)->firstOrFail()->enabled)->toBeFalse();
});

it('allows admin to create a new brand integration when brand does not exist', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.brands')
        ->call('openCreateIntegrationModal')
        ->set('newBrandName', 'Afrodita')
        ->set('newBrandExternalId', 11001)
        ->set('newBrandToken', '1|Hn1CJejtCfT0D6bdzMnOYO6beVDB1dvJEwTAvU9o27667b15')
        ->call('createBrandIntegration')
        ->assertHasNoErrors();

    $brand = Brand::query()->where('external_id', 11001)->firstOrFail();
    $integration = BrandWhitelist::query()->where('brand_id', $brand->id)->firstOrFail();

    expect($brand->name)->toBe('Afrodita');
    expect($integration->enabled)->toBeTrue();
    expect($integration->main_store_token)->toBe('1|Hn1CJejtCfT0D6bdzMnOYO6beVDB1dvJEwTAvU9o27667b15');
});
