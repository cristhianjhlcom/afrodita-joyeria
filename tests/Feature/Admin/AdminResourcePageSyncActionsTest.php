<?php

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('queues products sync from products page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'products',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.products')
        ->call('queueProductsSync')
        ->assertSet('syncQueued', true);
});

it('queues brands sync from brands page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'brands',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.brands')
        ->call('queueBrandsSync')
        ->assertSet('syncQueued', true);
});

it('queues categories sync from categories page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'categories',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.categories')
        ->call('queueCategoriesSync')
        ->assertSet('syncQueued', true);
});

it('queues countries sync from countries page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'countries',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.countries')
        ->call('queueCountriesSync')
        ->assertSet('syncQueued', true);
});

it('queues departments sync from departments page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'departments',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.departments')
        ->call('queueDepartmentsSync')
        ->assertSet('syncQueued', true);
});

it('queues provinces sync from provinces page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'provinces',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.provinces')
        ->call('queueProvincesSync')
        ->assertSet('syncQueued', true);
});

it('queues districts sync from districts page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'districts',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.districts')
        ->call('queueDistrictsSync')
        ->assertSet('syncQueued', true);
});

it('queues addresses sync from addresses page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'addresses',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.addresses')
        ->call('queueAddressesSync')
        ->assertSet('syncQueued', true);
});

it('queues inventory sync from inventory page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'inventory',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.inventory')
        ->call('queueInventorySync')
        ->assertSet('syncQueued', true);
});

it('shows products table with avatar and sync metadata', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $brand = Brand::factory()->create();
    $parentCategory = Category::factory()->create();
    $subcategory = Category::factory()->subcategory($parentCategory)->create();

    $product = Product::factory()->create([
        'name' => 'Ariel Ring',
        'brand_id' => $brand->id,
        'subcategory_id' => $subcategory->id,
    ]);

    ProductImage::factory()->create([
        'product_id' => $product->id,
        'url' => 'https://cdn.example.com/ariel-ring-table.jpg',
        'is_primary' => true,
    ]);

    SyncRun::factory()->completed()->create([
        'resource' => 'products',
        'checkpoint_updated_since' => now()->subMinutes(5),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products'))
        ->assertSuccessful()
        ->assertSee('Encolar sincronizacion')
        ->assertSee('Ultima sincronizacion')
        ->assertSee('Saludable')
        ->assertSee('https://cdn.example.com/ariel-ring-table.jpg');
});
