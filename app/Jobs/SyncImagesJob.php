<?php

namespace App\Jobs;

use App\Services\MainStore\MainStoreSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncImagesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(MainStoreSyncService $syncService): void
    {
        $syncService->syncImages();
    }
}
