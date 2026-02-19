<?php

namespace App\Console\Commands;

use App\Jobs\SyncBrandsJob;
use App\Jobs\SyncCategoriesJob;
use App\Jobs\SyncImagesJob;
use App\Jobs\SyncInventoryJob;
use App\Jobs\SyncOrdersJob;
use App\Jobs\SyncProductsJob;
use App\Jobs\SyncVariantsJob;
use App\Services\MainStore\MainStoreSyncService;
use Illuminate\Console\Command;

class SyncMainStoreCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'main-store:sync
                            {resource=all : all|brands|categories|products|variants|images|inventory|orders}
                            {--queued : Dispatch jobs instead of running sync inline}';

    /**
     * @var string
     */
    protected $description = 'Synchronize catalog and mirrored data from the main store';

    public function handle(MainStoreSyncService $syncService): int
    {
        $resource = (string) $this->argument('resource');
        $queued = (bool) $this->option('queued');
        $allowedResources = ['all', 'brands', 'categories', 'products', 'variants', 'images', 'inventory', 'orders'];

        if (! in_array($resource, $allowedResources, true)) {
            $this->components->error("Unsupported resource [{$resource}].");

            return self::FAILURE;
        }

        if ($queued) {
            $this->dispatchResourceJobs($resource);
            $this->components->info('Main store sync jobs dispatched.');

            return self::SUCCESS;
        }

        $this->runInlineSync($syncService, $resource);
        $this->components->info('Main store sync completed.');

        return self::SUCCESS;
    }

    protected function dispatchResourceJobs(string $resource): void
    {
        match ($resource) {
            'all' => $this->dispatchAllJobs(),
            'brands' => SyncBrandsJob::dispatch(),
            'categories' => SyncCategoriesJob::dispatch(),
            'products' => SyncProductsJob::dispatch(),
            'variants' => SyncVariantsJob::dispatch(),
            'images' => SyncImagesJob::dispatch(),
            'inventory' => SyncInventoryJob::dispatch(),
            'orders' => SyncOrdersJob::dispatch(),
        };
    }

    protected function runInlineSync(MainStoreSyncService $syncService, string $resource): void
    {
        match ($resource) {
            'all' => $this->runAllInline($syncService),
            'brands' => $syncService->syncBrands(),
            'categories' => $syncService->syncCategories(),
            'products' => $syncService->syncProducts(),
            'variants' => $syncService->syncVariants(),
            'images' => $syncService->syncImages(),
            'inventory' => $syncService->syncInventory(),
            'orders' => $syncService->syncOrders(),
        };
    }

    protected function dispatchAllJobs(): void
    {
        SyncBrandsJob::dispatch();
        SyncCategoriesJob::dispatch();
        SyncProductsJob::dispatch();
        SyncVariantsJob::dispatch();
        SyncImagesJob::dispatch();
        SyncInventoryJob::dispatch();
        SyncOrdersJob::dispatch();
    }

    protected function runAllInline(MainStoreSyncService $syncService): void
    {
        $syncService->syncBrands();
        $syncService->syncCategories();
        $syncService->syncProducts();
        $syncService->syncVariants();
        $syncService->syncImages();
        $syncService->syncInventory();
        $syncService->syncOrders();
    }
}
