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
use App\Jobs\SyncOrderItemsJob;
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
                            {resource=all : all|brands|categories|products|variants|images|inventory|orders|order-items|countries|departments|provinces|districts|addresses}
                            {--queued : Dispatch jobs instead of running sync inline}
                            {--full : Run a full sync without updated_since checkpoints}';

    /**
     * @var string
     */
    protected $description = 'Synchronize catalog and mirrored data from the main store';

    public function handle(MainStoreSyncService $syncService): int
    {
        $resource = (string) $this->argument('resource');
        $queued = (bool) $this->option('queued');
        $forceFull = (bool) $this->option('full');
        $allowedResources = [
            'all',
            'brands',
            'categories',
            'products',
            'variants',
            'images',
            'inventory',
            'orders',
            'order-items',
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
            $this->dispatchResourceJobs($resource, $forceFull);
            $this->components->info('Main store sync jobs dispatched.');

            return self::SUCCESS;
        }

        $this->runInlineSync($syncService, $resource, $forceFull);
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

    protected function dispatchResourceJobs(string $resource, bool $forceFull): void
    {
        match ($resource) {
            'all' => $this->dispatchAllJobs($forceFull),
            'brands' => SyncBrandsJob::dispatch($forceFull),
            'categories' => SyncCategoriesJob::dispatch($forceFull),
            'products' => SyncProductsJob::dispatch($forceFull),
            'variants' => SyncVariantsJob::dispatch($forceFull),
            'images' => SyncImagesJob::dispatch($forceFull),
            'inventory' => SyncInventoryJob::dispatch($forceFull),
            'orders' => SyncOrdersJob::dispatch($forceFull),
            'order-items' => SyncOrderItemsJob::dispatch($forceFull),
            'countries' => SyncCountriesJob::dispatch($forceFull),
            'departments' => SyncDepartmentsJob::dispatch($forceFull),
            'provinces' => SyncProvincesJob::dispatch($forceFull),
            'districts' => SyncDistrictsJob::dispatch($forceFull),
            'addresses' => SyncAddressesJob::dispatch($forceFull),
        };
    }

    protected function runInlineSync(MainStoreSyncService $syncService, string $resource, bool $forceFull): void
    {
        match ($resource) {
            'all' => $this->runAllInline($syncService, $forceFull),
            'brands' => $syncService->syncBrands(forceFull: $forceFull),
            'categories' => $syncService->syncCategories(forceFull: $forceFull),
            'products' => $syncService->syncProducts(forceFull: $forceFull),
            'variants' => $syncService->syncVariants(forceFull: $forceFull),
            'images' => $syncService->syncImages(forceFull: $forceFull),
            'inventory' => $syncService->syncInventory(forceFull: $forceFull),
            'orders' => $syncService->syncOrders(forceFull: $forceFull),
            'order-items' => $syncService->syncOrderItems(forceFull: $forceFull),
            'countries' => $syncService->syncCountries(forceFull: $forceFull),
            'departments' => $syncService->syncDepartments(forceFull: $forceFull),
            'provinces' => $syncService->syncProvinces(forceFull: $forceFull),
            'districts' => $syncService->syncDistricts(forceFull: $forceFull),
            'addresses' => $syncService->syncAddresses(forceFull: $forceFull),
        };
    }

    protected function dispatchAllJobs(bool $forceFull): void
    {
        SyncBrandsJob::dispatch($forceFull);
        SyncCategoriesJob::dispatch($forceFull);
        SyncProductsJob::dispatch($forceFull);
        SyncVariantsJob::dispatch($forceFull);
        SyncImagesJob::dispatch($forceFull);
        SyncInventoryJob::dispatch($forceFull);
        SyncOrdersJob::dispatch($forceFull);
        SyncOrderItemsJob::dispatch($forceFull);
        SyncCountriesJob::dispatch($forceFull);
        SyncDepartmentsJob::dispatch($forceFull);
        SyncProvincesJob::dispatch($forceFull);
        SyncDistrictsJob::dispatch($forceFull);
        SyncAddressesJob::dispatch($forceFull);
    }

    protected function runAllInline(MainStoreSyncService $syncService, bool $forceFull): void
    {
        $syncService->syncBrands(forceFull: $forceFull);
        $syncService->syncCategories(forceFull: $forceFull);
        $syncService->syncProducts(forceFull: $forceFull);
        $syncService->syncVariants(forceFull: $forceFull);
        $syncService->syncImages(forceFull: $forceFull);
        $syncService->syncInventory(forceFull: $forceFull);
        $syncService->syncOrders(forceFull: $forceFull);
        $syncService->syncOrderItems(forceFull: $forceFull);
        $syncService->syncCountries(forceFull: $forceFull);
        $syncService->syncDepartments(forceFull: $forceFull);
        $syncService->syncProvinces(forceFull: $forceFull);
        $syncService->syncDistricts(forceFull: $forceFull);
        $syncService->syncAddresses(forceFull: $forceFull);
    }
}
