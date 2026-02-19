<?php

use App\Enums\UserRole;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('queues resource sync from admin dashboard actions', function () {
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
        ->test('pages::admin.dashboard')
        ->call('queueResourceSync', 'products')
        ->assertSet('queuedResource', 'products');
});

it('ignores unsupported resource sync trigger values', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Artisan::spy();

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->call('queueResourceSync', 'unsupported-resource')
        ->assertSet('queuedResource', null);

    Artisan::shouldNotHaveReceived('call');
});

it('shows retry button for failed resource runs', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    SyncRun::factory()->failed()->create([
        'resource' => 'products',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('Resource Sync Controls')
        ->assertSee('Retry Failed');
});
