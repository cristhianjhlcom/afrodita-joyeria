<?php

use App\Enums\UserRole;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('queues full sync from admin dashboard action', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('main-store:sync', [
            'resource' => 'all',
            '--queued' => true,
        ])
        ->andReturn(0);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->call('queueSync')
        ->assertSet('syncQueued', true);
});

it('shows sync monitoring sections without resource control table', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    SyncRun::factory()->failed()->create([
        'resource' => 'products',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('Recent Sync Runs')
        ->assertSee('Queue Full Sync')
        ->assertDontSee('Resource Sync Controls');
});
