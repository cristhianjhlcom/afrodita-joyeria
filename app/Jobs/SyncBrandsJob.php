<?php

namespace App\Jobs;

use App\Services\MainStore\MainStoreSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBrandsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(public bool $forceFull = false) {}

    public function handle(MainStoreSyncService $syncService): void
    {
        $syncService->syncBrands(forceFull: $this->forceFull);
    }
}
