<?php

use App\Enums\UserRole;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders sync run details and error payload for admin users', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $syncRun = SyncRun::factory()->failed()->create([
        'resource' => 'products',
        'records_processed' => 24,
        'errors_count' => 2,
        'meta' => [
            'error' => 'API request failed with timeout.',
            'errors' => ['First batch timed out.', 'Retry exceeded max attempts.'],
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.sync-runs.show', $syncRun))
        ->assertSuccessful()
        ->assertSee('Sync Run #'.$syncRun->id)
        ->assertSee('Products')
        ->assertSee('API request failed with timeout.')
        ->assertSee('First batch timed out.')
        ->assertSee('Retry exceeded max attempts.');
});

it('shows sync run detail action in admin dashboard table', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $syncRun = SyncRun::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee(route('admin.sync-runs.show', $syncRun));
});
