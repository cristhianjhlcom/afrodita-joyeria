<?php

use App\Enums\UserRole;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows stale sync warning when required resources are missing successful sync runs', function () {
    config()->set('services.main_store.stale_threshold_minutes', 60);

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    SyncRun::factory()->completed()->create([
        'resource' => 'brands',
        'checkpoint_updated_since' => now()->subMinutes(10),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('Los datos de sincronizacion estan desactualizados')
        ->assertSee('Ejecuciones exitosas faltantes');
});

it('shows healthy sync status when all resources are fresh', function () {
    config()->set('services.main_store.stale_threshold_minutes', 60);

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    foreach (['brands', 'categories', 'products', 'variant-images', 'variants', 'inventory', 'orders'] as $resource) {
        SyncRun::factory()->completed()->create([
            'resource' => $resource,
            'checkpoint_updated_since' => now()->subMinutes(5),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('Los datos de sincronizacion estan saludables')
        ->assertDontSee('Los datos de sincronizacion estan desactualizados');
});
