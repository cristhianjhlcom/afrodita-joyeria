<?php

use App\Enums\UserRole;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows repeated failure alert when threshold is reached', function () {
    config()->set('services.main_store.failure_alert_threshold', 3);

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    SyncRun::factory()->failed()->create([
        'resource' => 'products',
        'started_at' => now()->subMinutes(1),
    ]);
    SyncRun::factory()->failed()->create([
        'resource' => 'products',
        'started_at' => now()->subMinutes(2),
    ]);
    SyncRun::factory()->failed()->create([
        'resource' => 'products',
        'started_at' => now()->subMinutes(3),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('Repeated sync failures detected')
        ->assertSee('Products (3)');
});

it('does not show repeated failure alert when failures are below threshold', function () {
    config()->set('services.main_store.failure_alert_threshold', 3);

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    SyncRun::factory()->failed()->create([
        'resource' => 'products',
        'started_at' => now()->subMinutes(1),
    ]);
    SyncRun::factory()->failed()->create([
        'resource' => 'products',
        'started_at' => now()->subMinutes(2),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('Repeated sync failures detected');
});
