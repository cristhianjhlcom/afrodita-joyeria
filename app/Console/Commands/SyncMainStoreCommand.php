<?php

namespace App\Console\Commands;

use App\Jobs\SyncAddressesJob;
use App\Jobs\SyncBrandsJob;
use App\Jobs\SyncCategoriesJob;
use App\Jobs\SyncCountriesJob;
use App\Jobs\SyncDepartmentsJob;
use App\Jobs\SyncDistrictsJob;
use App\Jobs\SyncImagesJob;
use App\Jobs\SyncInventoryJob;
use App\Jobs\SyncOrdersJob;
use App\Jobs\SyncProductsJob;
use App\Jobs\SyncProvincesJob;
use App\Jobs\SyncVariantsJob;
use App\Models\BrandWhitelist;
use App\Services\MainStore\MainStoreSyncService;
use Illuminate\Console\Command;

class SyncMainStoreCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'main-store:sync
                            {resource=all : all|brands|categories|products|variants|images|inventory|orders|countries|departments|provinces|districts|addresses}
                            {--queued : Dispatch jobs instead of running sync inline}';

    /**
     * @var string
     */
    protected $description = 'Synchronize catalog and mirrored data from the main store';

    public function handle(MainStoreSyncService $syncService): int
    {
        $resource = (string) $this->argument('resource');
        $queued = (bool) $this->option('queued');
        $allowedResources = [
            'all',
            'brands',
            'categories',
            'products',
            'variants',
            'images',
            'inventory',
            'orders',
            'countries',
            'departments',
            'provinces',
            'districts',
            'addresses',
        ];

        if (! in_array($resource, $allowedResources, true)) {
            $this->components->error("Unsupported resource [{$resource}].");

            return self::FAILURE;
        }

        if (! $this->validateMainStoreConfiguration()) {
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

    protected function validateMainStoreConfiguration(): bool
    {
        $baseUrl = trim((string) config('services.main_store.base_url'));
        $fallbackToken = trim((string) config('services.main_store.token'));

        if ($baseUrl === '') {
            $this->components->error('MAIN_STORE_BASE_URL is missing. Set it before running sync.');

            return false;
        }

        $hasEnabledBrandToken = BrandWhitelist::query()
            ->where('enabled', true)
            ->whereNotNull('main_store_token')
            ->exists();

        if (! $hasEnabledBrandToken && $fallbackToken === '') {
            $this->components->error('No main store token configured. Save a token in Admin > Brands for an enabled brand or set MAIN_STORE_TOKEN.');

            return false;
        }

        return true;
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
            'countries' => SyncCountriesJob::dispatch(),
            'departments' => SyncDepartmentsJob::dispatch(),
            'provinces' => SyncProvincesJob::dispatch(),
            'districts' => SyncDistrictsJob::dispatch(),
            'addresses' => SyncAddressesJob::dispatch(),
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
            'countries' => $syncService->syncCountries(),
            'departments' => $syncService->syncDepartments(),
            'provinces' => $syncService->syncProvinces(),
            'districts' => $syncService->syncDistricts(),
            'addresses' => $syncService->syncAddresses(),
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
        SyncCountriesJob::dispatch();
        SyncDepartmentsJob::dispatch();
        SyncProvincesJob::dispatch();
        SyncDistrictsJob::dispatch();
        SyncAddressesJob::dispatch();
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
        $syncService->syncCountries();
        $syncService->syncDepartments();
        $syncService->syncProvinces();
        $syncService->syncDistricts();
        $syncService->syncAddresses();
    }
}
