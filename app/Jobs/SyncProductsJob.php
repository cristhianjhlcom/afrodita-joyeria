<?php

namespace App\Jobs;

use App\Services\MainStore\MainStoreSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncProductsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(public bool $forceFull = false) {}

    public function handle(MainStoreSyncService $syncService): void
    {
        $syncService->syncProducts(forceFull: $this->forceFull);
    }
}
