<?php

namespace App\Services\MainStore;

use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\Category;
use App\Models\Country;
use App\Models\Department;
use App\Models\District;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MainStoreSyncService
{
    public function __construct(protected MainStoreApiClient $client) {}

    public function syncBrands(bool $includeDisabled = false): int
    {
        return $this->syncResource('brands', function (array $items) use ($includeDisabled): int {
            $brands = collect($items)
                ->map(function (array $item): array {
                    return [
                        'external_id' => (int) $item['id'],
                        'name' => (string) ($item['name'] ?? ''),
                        'slug' => $item['slug'] ?? null,
                        'is_active' => (bool) ($item['is_active'] ?? true),
                        'deleted_at' => $this->normalizeTimestamp($item['deleted_at'] ?? null),
                        'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                        'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
                    ];
                })
                ->values();

            Brand::query()->upsert(
                $brands->all(),
                ['external_id'],
                ['name', 'slug', 'is_active', 'deleted_at', 'updated_at', 'created_at'],
            );

            if (! $includeDisabled) {
                $brandIds = Brand::query()
                    ->whereIn('external_id', $brands->pluck('external_id'))
                    ->pluck('id');

                $rows = $brandIds->map(fn (int $brandId): array => [
                    'brand_id' => $brandId,
                    'enabled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                if ($rows !== []) {
                    BrandWhitelist::query()->upsert($rows, ['brand_id'], ['updated_at']);
                }
            }

            return $brands->count();
        });
    }

    public function syncCategories(): int
    {
        $syncRun = SyncRun::query()->create([
            'resource' => 'categories',
            'status' => 'running',
            'started_at' => now(),
            'records_processed' => 0,
            'errors_count' => 0,
        ]);

        $processed = 0;
        $checkpoint = $this->resolveCheckpoint('categories');

        try {
            $processed += $this->syncCategoryResource('categories', 'parent_id', $checkpoint, false);
            $processed += $this->syncCategoryResource('subcategories', 'category_id', $checkpoint, true);

            $syncRun->update([
                'status' => 'completed',
                'finished_at' => now(),
                'records_processed' => $processed,
                'checkpoint_updated_since' => now(),
            ]);
        } catch (\Throwable $throwable) {
            $syncRun->update([
                'status' => 'failed',
                'finished_at' => now(),
                'records_processed' => $processed,
                'errors_count' => 1,
                'meta' => ['error' => $throwable->getMessage()],
            ]);

            throw $throwable;
        }

        return $processed;
    }

    public function syncProducts(): int
    {
        $tokenBrandScopes = $this->resolveTokenBrandScopes();
        $seenProductsByToken = [];
        $seenVariantsByToken = [];
        $seenImagesByToken = [];
        $scopedBrandsByToken = [];

        return $this->syncResource('products', function (array $items, string $token) use (
            $tokenBrandScopes,
            &$seenProductsByToken,
            &$seenVariantsByToken,
            &$seenImagesByToken,
            &$scopedBrandsByToken
        ): int {
            $brandMap = Brand::query()->pluck('id', 'external_id');
            $categoryMap = Category::query()->pluck('id', 'external_id');
            $categoryBySlug = Category::query()->pluck('id', 'slug');
            $categoryByName = Category::query()->pluck('id', 'name');
            $categoryByNameLower = Category::query()
                ->get(['id', 'name'])
                ->mapWithKeys(fn (Category $category): array => [Str::lower((string) $category->name) => $category->id]);
            $whitelistedBrandIds = BrandWhitelist::query()
                ->where('enabled', true)
                ->pluck('brand_id')
                ->all();
            $brandBySlug = Brand::query()->pluck('id', 'slug');
            $brandByName = Brand::query()->pluck('id', 'name');
            $brandByNameLower = Brand::query()
                ->get(['id', 'name'])
                ->mapWithKeys(fn (Brand $brand): array => [Str::lower((string) $brand->name) => $brand->id]);

            $scopedBrandIds = $tokenBrandScopes[$token] ?? [];
            $scopedBrandsByToken[$token] = $scopedBrandIds;

            $rows = collect();
            $variantRows = collect();
            $imageRows = collect();
            $seenProductRefs = [];
            $seenVariantRefs = [];
            $seenImageRefs = [];

            foreach ($items as $item) {
                $brandId = $brandMap->get((int) ($item['brand_id'] ?? 0));
                if ($brandId === null) {
                    $brandId = $this->resolveBrandIdFromNestedPayload($item, $brandBySlug, $brandByName, $brandByNameLower);
                }

                $categoryId = $categoryMap->get((int) ($item['category_id'] ?? 0));
                if ($categoryId === null) {
                    $categoryId = $this->resolveCategoryIdFromNestedPayload($item);
                }

                $subcategoryId = $categoryMap->get((int) ($item['subcategory_id'] ?? 0));
                if ($subcategoryId === null) {
                    $subcategoryId = $this->resolveSubcategoryIdFromNestedPayload(
                        $item,
                        $categoryBySlug,
                        $categoryByName,
                        $categoryByNameLower,
                        $categoryId
                    );
                }

                if ($brandId === null || $subcategoryId === null) {
                    continue;
                }

                if (! in_array((int) $brandId, $whitelistedBrandIds, true)) {
                    continue;
                }

                if ($scopedBrandIds !== [] && ! in_array((int) $brandId, $scopedBrandIds, true)) {
                    continue;
                }

                $productExternalId = $this->resolveNumericExternalId($item['id'] ?? null);
                $productExternalRef = $this->resolveExternalRef($item, "product:{$brandId}:".($item['slug'] ?? ''));

                $rows->push([
                    'external_id' => $productExternalId,
                    'external_ref' => $productExternalRef,
                    'brand_id' => (int) $brandId,
                    'category_id' => $categoryId,
                    'subcategory_id' => (int) $subcategoryId,
                    'name' => (string) ($item['name'] ?? ''),
                    'slug' => (string) ($item['slug'] ?? "product-{$productExternalRef}"),
                    'description' => $item['description'] ?? null,
                    'status' => (string) ($item['status'] ?? Product::STATUS_DRAFT),
                    'sort_order' => (int) ($item['order'] ?? 0),
                    'url' => Arr::get($item, 'url'),
                    'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                    'deleted_at' => $this->normalizeTimestamp($item['deleted_at'] ?? null),
                    'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                    'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
                ]);
                $seenProductRefs[] = $productExternalRef;

                foreach ($this->normalizeUrlList(Arr::get($item, 'images', [])) as $imageIndex => $imageUrl) {
                    $imageExternalRef = $this->resolveImageExternalRef("product:{$productExternalRef}", $imageUrl, $imageIndex);

                    $imageRows->push([
                        'external_ref' => $imageExternalRef,
                        'product_external_ref' => $productExternalRef,
                        'variant_external_ref' => null,
                        'url' => $imageUrl,
                        'sort_order' => $imageIndex,
                        'is_primary' => $imageIndex === 0,
                        'alt' => null,
                        'deleted_at' => null,
                        'updated_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ]);

                    $seenImageRefs[] = $imageExternalRef;
                }

                foreach (($item['variants'] ?? []) as $variantIndex => $variant) {
                    if (! is_array($variant)) {
                        continue;
                    }

                    $variantExternalId = $this->resolveNumericExternalId($variant['id'] ?? null);
                    $variantExternalRef = $this->resolveExternalRef(
                        $variant,
                        "variant:{$productExternalRef}:".($variant['sku'] ?? $variant['code'] ?? "index-{$variantIndex}")
                    );

                    $variantRows->push([
                        'external_id' => $variantExternalId,
                        'external_ref' => $variantExternalRef,
                        'product_external_ref' => $productExternalRef,
                        'sku' => $variant['sku'] ?? null,
                        'code' => $variant['code'] ?? null,
                        'price' => Arr::get($variant, 'price'),
                        'sale_price' => Arr::get($variant, 'sale_price'),
                        'color' => $variant['color'] ?? null,
                        'hex' => $variant['hex'] ?? null,
                        'size' => $variant['size'] ?? null,
                        'primary_image_url' => Arr::get($variant, 'image'),
                        'stock_available' => (int) ($variant['stock'] ?? 0),
                        'stock_on_hand' => (int) ($variant['stock'] ?? 0),
                        'stock_reserved' => 0,
                        'is_active' => (bool) ($variant['in_stock'] ?? true),
                        'remote_updated_at' => $this->normalizeTimestamp($variant['updated_at'] ?? $item['updated_at'] ?? null),
                        'updated_at' => $this->normalizeTimestamp($variant['updated_at'] ?? $item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                        'created_at' => $this->normalizeTimestamp($variant['created_at'] ?? $item['created_at'] ?? null) ?? now()->toDateTimeString(),
                        'deleted_at' => $this->normalizeTimestamp($variant['deleted_at'] ?? null),
                    ]);
                    $seenVariantRefs[] = $variantExternalRef;

                    $variantImageUrls = $this->normalizeVariantImageList($variant);
                    foreach ($variantImageUrls as $variantImageIndex => $variantImageUrl) {
                        $imageExternalRef = $this->resolveImageExternalRef("variant:{$variantExternalRef}", $variantImageUrl, $variantImageIndex);

                        $imageRows->push([
                            'external_ref' => $imageExternalRef,
                            'product_external_ref' => $productExternalRef,
                            'variant_external_ref' => $variantExternalRef,
                            'url' => $variantImageUrl,
                            'sort_order' => $variantImageIndex,
                            'is_primary' => $variantImageIndex === 0,
                            'alt' => null,
                            'deleted_at' => null,
                            'updated_at' => now()->toDateTimeString(),
                            'created_at' => now()->toDateTimeString(),
                        ]);

                        $seenImageRefs[] = $imageExternalRef;
                    }
                }
            }

            $rows = $rows->unique('external_ref')->values();

            if ($rows->isEmpty()) {
                $seenProductsByToken[$token] ??= [];
                $seenVariantsByToken[$token] ??= [];
                $seenImagesByToken[$token] ??= [];

                return 0;
            }

            Product::query()->upsert(
                $rows->all(),
                ['external_ref'],
                ['external_id', 'brand_id', 'category_id', 'subcategory_id', 'name', 'slug', 'description', 'status', 'sort_order', 'url', 'remote_updated_at', 'deleted_at', 'updated_at', 'created_at'],
            );

            if ($variantRows->isNotEmpty()) {
                $productIdMap = Product::query()
                    ->whereIn('external_ref', $rows->pluck('external_ref'))
                    ->pluck('id', 'external_ref');

                $preparedVariantRows = $variantRows
                    ->map(function (array $variantRow) use ($productIdMap): ?array {
                        $productId = $productIdMap->get((string) $variantRow['product_external_ref']);
                        if ($productId === null) {
                            return null;
                        }

                        return Arr::except($variantRow, ['product_external_ref']) + ['product_id' => $productId];
                    })
                    ->filter()
                    ->unique('external_ref')
                    ->values();

                if ($preparedVariantRows->isNotEmpty()) {
                    ProductVariant::query()->upsert(
                        $preparedVariantRows->all(),
                        ['external_ref'],
                        ['external_id', 'product_id', 'sku', 'code', 'price', 'sale_price', 'color', 'hex', 'size', 'primary_image_url', 'stock_on_hand', 'stock_reserved', 'stock_available', 'is_active', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
                    );
                }
            }

            if ($imageRows->isNotEmpty()) {
                $productIdMap = Product::query()
                    ->whereIn('external_ref', $rows->pluck('external_ref'))
                    ->pluck('id', 'external_ref');
                $variantIdMap = ProductVariant::query()
                    ->whereIn('external_ref', $variantRows->pluck('external_ref'))
                    ->pluck('id', 'external_ref');

                $preparedImageRows = $imageRows
                    ->map(function (array $imageRow) use ($productIdMap, $variantIdMap): ?array {
                        $productId = $productIdMap->get((string) $imageRow['product_external_ref']);
                        if ($productId === null) {
                            return null;
                        }

                        $variantId = null;
                        if ($imageRow['variant_external_ref'] !== null) {
                            $variantId = $variantIdMap->get((string) $imageRow['variant_external_ref']);
                        }

                        return Arr::except($imageRow, ['product_external_ref', 'variant_external_ref']) + [
                            'product_id' => $productId,
                            'product_variant_id' => $variantId,
                        ];
                    })
                    ->filter()
                    ->unique('external_ref')
                    ->values();

                if ($preparedImageRows->isNotEmpty()) {
                    ProductImage::query()->upsert(
                        $preparedImageRows->all(),
                        ['external_ref'],
                        ['product_id', 'product_variant_id', 'url', 'sort_order', 'alt', 'is_primary', 'deleted_at', 'updated_at', 'created_at'],
                    );
                }
            }

            $seenProductsByToken[$token] = array_values(array_unique(array_merge($seenProductsByToken[$token] ?? [], $seenProductRefs)));
            $seenVariantsByToken[$token] = array_values(array_unique(array_merge($seenVariantsByToken[$token] ?? [], $seenVariantRefs)));
            $seenImagesByToken[$token] = array_values(array_unique(array_merge($seenImagesByToken[$token] ?? [], $seenImageRefs)));

            return $rows->count();
        }, useCheckpoint: false, afterToken: function (string $token) use (&$seenProductsByToken, &$seenVariantsByToken, &$seenImagesByToken, &$scopedBrandsByToken): void {
            $this->softDeleteMissingCatalogRecords(
                $scopedBrandsByToken[$token] ?? [],
                $seenProductsByToken[$token] ?? [],
                $seenVariantsByToken[$token] ?? [],
                $seenImagesByToken[$token] ?? [],
            );
        });
    }

    public function syncVariants(): int
    {
        return $this->syncResource('variants', function (array $items): int {
            $productMap = Product::query()->pluck('id', 'external_id');

            $rows = collect($items)
                ->map(function (array $item) use ($productMap): ?array {
                    $productId = $productMap->get((int) ($item['product_id'] ?? 0));

                    if ($productId === null) {
                        return null;
                    }

                    $externalId = $this->resolveNumericExternalId($item['id'] ?? null);
                    $externalRef = $this->resolveExternalRef($item, "variant:{$productId}:".($item['sku'] ?? $item['code'] ?? ''));

                    return [
                        'external_id' => $externalId,
                        'external_ref' => $externalRef,
                        'product_id' => $productId,
                        'sku' => $item['sku'] ?? null,
                        'code' => $item['code'] ?? null,
                        'price' => Arr::get($item, 'price'),
                        'sale_price' => Arr::get($item, 'sale_price'),
                        'color' => $item['color'] ?? null,
                        'hex' => $item['hex'] ?? null,
                        'size' => $item['size'] ?? null,
                        'primary_image_url' => Arr::get($item, 'image'),
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                        'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
                        'deleted_at' => $this->normalizeTimestamp($item['deleted_at'] ?? null),
                    ];
                })
                ->filter()
                ->values();

            if ($rows->isEmpty()) {
                return 0;
            }

            ProductVariant::query()->upsert(
                $rows->all(),
                ['external_ref'],
                ['external_id', 'product_id', 'sku', 'code', 'price', 'sale_price', 'color', 'hex', 'size', 'primary_image_url', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
            );

            return $rows->count();
        });
    }

    public function syncImages(): int
    {
        return $this->syncResource('variant-images', function (array $items): int {
            $productMap = Product::query()->pluck('id', 'external_id');
            $variantMap = ProductVariant::query()->pluck('id', 'external_id');

            $rows = collect($items)
                ->map(function (array $item) use ($productMap, $variantMap): ?array {
                    $productId = $productMap->get((int) ($item['product_id'] ?? 0));

                    if ($productId === null) {
                        return null;
                    }

                    $variantId = null;
                    if (($item['variant_id'] ?? null) !== null) {
                        $variantId = $variantMap->get((int) $item['variant_id']);
                    }

                    return [
                        'external_ref' => $this->resolveExternalRef($item, "image:{$productId}:".($variantId ?? 'none').':'.((string) ($item['url'] ?? ''))),
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'url' => (string) ($item['url'] ?? ''),
                        'sort_order' => (int) ($item['sort_order'] ?? 0),
                        'alt' => $item['alt'] ?? null,
                        'is_primary' => (bool) ($item['is_primary'] ?? false),
                        'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                        'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
                        'deleted_at' => $this->normalizeTimestamp($item['deleted_at'] ?? null),
                    ];
                })
                ->filter(fn (?array $row): bool => $row !== null && $row['url'] !== '')
                ->values();

            if ($rows->isEmpty()) {
                return 0;
            }

            ProductImage::query()->upsert(
                $rows->all(),
                ['external_ref'],
                ['product_id', 'product_variant_id', 'url', 'sort_order', 'alt', 'is_primary', 'updated_at', 'created_at', 'deleted_at'],
            );

            return $rows->count();
        }, allowMissing: true);
    }

    public function syncInventory(): int
    {
        return $this->syncResource('inventory', function (array $items): int {
            $variantMap = ProductVariant::query()->pluck('id', 'external_id');
            $updated = 0;

            foreach ($items as $item) {
                $variantId = $variantMap->get((int) ($item['variant_id'] ?? 0));
                if ($variantId === null) {
                    continue;
                }

                ProductVariant::query()->whereKey($variantId)->update([
                    'stock_on_hand' => (int) ($item['stock_on_hand'] ?? 0),
                    'stock_reserved' => (int) ($item['stock_reserved'] ?? 0),
                    'stock_available' => (int) ($item['stock_available'] ?? 0),
                    'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                ]);

                $updated++;
            }

            return $updated;
        });
    }

    public function syncOrders(): int
    {
        return $this->syncResource('orders', function (array $items): int {
            $orders = collect($items)
                ->map(fn (array $item): array => [
                    'external_id' => (int) $item['id'],
                    'external_customer_id' => Arr::get($item, 'customer_id'),
                    'status' => (string) ($item['status'] ?? 'pending'),
                    'currency' => (string) ($item['currency'] ?? 'USD'),
                    'subtotal' => (int) ($item['subtotal'] ?? 0),
                    'discount_total' => (int) ($item['discount_total'] ?? 0),
                    'shipping_total' => (int) ($item['shipping_total'] ?? 0),
                    'tax_total' => (int) ($item['tax_total'] ?? 0),
                    'grand_total' => (int) ($item['grand_total'] ?? 0),
                    'placed_at' => $this->normalizeTimestamp($item['placed_at'] ?? null),
                    'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                    'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
                ])
                ->values();

            if ($orders->isEmpty()) {
                return 0;
            }

            Order::query()->upsert(
                $orders->all(),
                ['external_id'],
                ['external_customer_id', 'status', 'currency', 'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'grand_total', 'placed_at', 'updated_at', 'created_at'],
            );

            $localOrders = Order::query()
                ->whereIn('external_id', $orders->pluck('external_id'))
                ->get(['id', 'external_id'])
                ->keyBy('external_id');

            foreach ($items as $item) {
                $order = $localOrders->get((int) $item['id']);
                if (! $order) {
                    continue;
                }

                $orderItems = collect($item['items'] ?? [])
                    ->map(fn (array $orderItem): array => [
                        'order_id' => $order->id,
                        'variant_external_id' => Arr::get($orderItem, 'variant_id'),
                        'sku' => $orderItem['sku'] ?? null,
                        'name_snapshot' => (string) ($orderItem['name_snapshot'] ?? 'Unknown item'),
                        'qty' => (int) ($orderItem['qty'] ?? 1),
                        'unit_price' => (int) ($orderItem['unit_price'] ?? 0),
                        'line_total' => (int) ($orderItem['line_total'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->all();

                OrderItem::query()->where('order_id', $order->id)->delete();
                if ($orderItems !== []) {
                    OrderItem::query()->insert($orderItems);
                }
            }

            return $orders->count();
        });
    }

    public function syncCountries(): int
    {
        return $this->syncResource('countries', function (array $items): int {
            $rows = collect($items)
                ->map(function (array $item): ?array {
                    $externalId = $this->resolveNumericExternalId($item['id'] ?? null);
                    if ($externalId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'name' => (string) ($item['name'] ?? ''),
                        'iso_code_2' => Arr::get($item, 'iso_code_2'),
                        'iso_code_3' => Arr::get($item, 'iso_code_3'),
                        'is_active' => (bool) ($item['is_active'] ?? true),
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                        'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
                        'deleted_at' => $this->normalizeTimestamp($item['deleted_at'] ?? null),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($rows->isEmpty()) {
                return 0;
            }

            Country::query()->upsert(
                $rows->all(),
                ['external_id'],
                ['name', 'iso_code_2', 'iso_code_3', 'is_active', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
            );

            return $rows->count();
        });
    }

    public function syncDepartments(): int
    {
        return $this->syncResource('departments', function (array $items): int {
            $countryRows = collect($items)
                ->map(function (array $item): ?array {
                    $country = Arr::get($item, 'country');
                    if (! is_array($country)) {
                        return null;
                    }

                    $externalId = $this->resolveNumericExternalId($country['id'] ?? null);
                    if ($externalId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'name' => (string) ($country['name'] ?? ''),
                        'iso_code_2' => Arr::get($country, 'iso_code_2'),
                        'iso_code_3' => Arr::get($country, 'iso_code_3'),
                        'is_active' => true,
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($countryRows->isNotEmpty()) {
                Country::query()->upsert(
                    $countryRows->all(),
                    ['external_id'],
                    ['name', 'iso_code_2', 'iso_code_3', 'is_active', 'remote_updated_at', 'updated_at', 'created_at'],
                );
            }

            $countryMap = Country::query()->pluck('id', 'external_id');

            $rows = collect($items)
                ->map(function (array $item) use ($countryMap): ?array {
                    $externalId = $this->resolveNumericExternalId($item['id'] ?? null);
                    $countryExternalId = $this->resolveNumericExternalId($item['country_id'] ?? Arr::get($item, 'country.id'));

                    if ($externalId === null || $countryExternalId === null) {
                        return null;
                    }

                    $countryId = $countryMap->get($countryExternalId);
                    if ($countryId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'country_id' => (int) $countryId,
                        'name' => (string) ($item['name'] ?? ''),
                        'ubigeo_code' => Arr::get($item, 'ubigeo_code'),
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                        'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
                        'deleted_at' => $this->normalizeTimestamp($item['deleted_at'] ?? null),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($rows->isEmpty()) {
                return 0;
            }

            Department::query()->upsert(
                $rows->all(),
                ['external_id'],
                ['country_id', 'name', 'ubigeo_code', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
            );

            return $rows->count();
        });
    }

    public function syncProvinces(): int
    {
        return $this->syncResource('provinces', function (array $items): int {
            $countryRows = collect($items)
                ->map(function (array $item): ?array {
                    $country = Arr::get($item, 'country');
                    if (! is_array($country)) {
                        return null;
                    }

                    $externalId = $this->resolveNumericExternalId($country['id'] ?? null);
                    if ($externalId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'name' => (string) ($country['name'] ?? ''),
                        'iso_code_2' => Arr::get($country, 'iso_code_2'),
                        'iso_code_3' => Arr::get($country, 'iso_code_3'),
                        'is_active' => true,
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($countryRows->isNotEmpty()) {
                Country::query()->upsert(
                    $countryRows->all(),
                    ['external_id'],
                    ['name', 'iso_code_2', 'iso_code_3', 'is_active', 'remote_updated_at', 'updated_at', 'created_at'],
                );
            }

            $countryMap = Country::query()->pluck('id', 'external_id');

            $departmentRows = collect($items)
                ->map(function (array $item) use ($countryMap): ?array {
                    $department = Arr::get($item, 'department');
                    if (! is_array($department)) {
                        return null;
                    }

                    $externalId = $this->resolveNumericExternalId($department['id'] ?? null);
                    $countryExternalId = $this->resolveNumericExternalId(
                        $department['country_id'] ?? Arr::get($item, 'country_id') ?? Arr::get($item, 'country.id')
                    );

                    if ($externalId === null || $countryExternalId === null) {
                        return null;
                    }

                    $countryId = $countryMap->get($countryExternalId);
                    if ($countryId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'country_id' => (int) $countryId,
                        'name' => (string) ($department['name'] ?? ''),
                        'ubigeo_code' => Arr::get($department, 'ubigeo_code'),
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($departmentRows->isNotEmpty()) {
                Department::query()->upsert(
                    $departmentRows->all(),
                    ['external_id'],
                    ['country_id', 'name', 'ubigeo_code', 'remote_updated_at', 'updated_at', 'created_at'],
                );
            }

            $departmentMap = Department::query()->pluck('id', 'external_id');

            $rows = collect($items)
                ->map(function (array $item) use ($departmentMap, $countryMap): ?array {
                    $externalId = $this->resolveNumericExternalId($item['id'] ?? null);
                    $countryExternalId = $this->resolveNumericExternalId(
                        Arr::get($item, 'country.id') ?? Arr::get($item, 'country_id') ?? Arr::get($item, 'department.country_id')
                    );
                    $departmentExternalId = $this->resolveNumericExternalId($item['department_id'] ?? Arr::get($item, 'department.id'));

                    if ($externalId === null || $countryExternalId === null || $departmentExternalId === null) {
                        return null;
                    }

                    $countryId = $countryMap->get($countryExternalId);
                    $departmentId = $departmentMap->get($departmentExternalId);
                    if ($countryId === null || $departmentId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'country_id' => (int) $countryId,
                        'department_id' => (int) $departmentId,
                        'name' => (string) ($item['name'] ?? ''),
                        'ubigeo_code' => Arr::get($item, 'ubigeo_code'),
                        'shipping_price' => $this->normalizeMoneyToMinorAmount(Arr::get($item, 'shipping_price')),
                        'is_active' => (bool) ($item['is_active'] ?? true),
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                        'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
                        'deleted_at' => $this->normalizeTimestamp($item['deleted_at'] ?? null),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($rows->isEmpty()) {
                return 0;
            }

            Province::query()->upsert(
                $rows->all(),
                ['external_id'],
                ['country_id', 'department_id', 'name', 'ubigeo_code', 'shipping_price', 'is_active', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
            );

            return $rows->count();
        });
    }

    public function syncDistricts(): int
    {
        return $this->syncResource('districts', function (array $items): int {
            $countryRows = collect($items)
                ->map(function (array $item): ?array {
                    $country = Arr::get($item, 'country');
                    if (! is_array($country)) {
                        return null;
                    }

                    $externalId = $this->resolveNumericExternalId($country['id'] ?? null);
                    if ($externalId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'name' => (string) ($country['name'] ?? ''),
                        'iso_code_2' => Arr::get($country, 'iso_code_2'),
                        'iso_code_3' => Arr::get($country, 'iso_code_3'),
                        'is_active' => true,
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($countryRows->isNotEmpty()) {
                Country::query()->upsert(
                    $countryRows->all(),
                    ['external_id'],
                    ['name', 'iso_code_2', 'iso_code_3', 'is_active', 'remote_updated_at', 'updated_at', 'created_at'],
                );
            }

            $countryMap = Country::query()->pluck('id', 'external_id');

            $departmentRows = collect($items)
                ->map(function (array $item) use ($countryMap): ?array {
                    $department = Arr::get($item, 'department');
                    if (! is_array($department)) {
                        return null;
                    }

                    $externalId = $this->resolveNumericExternalId($department['id'] ?? null);
                    $countryExternalId = $this->resolveNumericExternalId(
                        $department['country_id'] ?? Arr::get($item, 'country.id')
                    );

                    if ($externalId === null || $countryExternalId === null) {
                        return null;
                    }

                    $countryId = $countryMap->get($countryExternalId);
                    if ($countryId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'country_id' => (int) $countryId,
                        'name' => (string) ($department['name'] ?? ''),
                        'ubigeo_code' => Arr::get($department, 'ubigeo_code'),
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($departmentRows->isNotEmpty()) {
                Department::query()->upsert(
                    $departmentRows->all(),
                    ['external_id'],
                    ['country_id', 'name', 'ubigeo_code', 'remote_updated_at', 'updated_at', 'created_at'],
                );
            }

            $departmentMap = Department::query()->pluck('id', 'external_id');

            $provinceRows = collect($items)
                ->map(function (array $item) use ($countryMap, $departmentMap): ?array {
                    $province = Arr::get($item, 'province');
                    if (! is_array($province)) {
                        return null;
                    }

                    $externalId = $this->resolveNumericExternalId($province['id'] ?? null);
                    $countryExternalId = $this->resolveNumericExternalId(
                        Arr::get($item, 'country.id') ?? Arr::get($item, 'department.country_id')
                    );
                    $departmentExternalId = $this->resolveNumericExternalId(
                        Arr::get($item, 'department.id') ?? Arr::get($province, 'department_id')
                    );

                    if ($externalId === null || $countryExternalId === null || $departmentExternalId === null) {
                        return null;
                    }

                    $countryId = $countryMap->get($countryExternalId);
                    $departmentId = $departmentMap->get($departmentExternalId);
                    if ($countryId === null || $departmentId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'country_id' => (int) $countryId,
                        'department_id' => (int) $departmentId,
                        'name' => (string) ($province['name'] ?? ''),
                        'ubigeo_code' => Arr::get($province, 'ubigeo_code'),
                        'shipping_price' => $this->normalizeMoneyToMinorAmount(Arr::get($province, 'shipping_price')),
                        'is_active' => (bool) ($province['is_active'] ?? true),
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($provinceRows->isNotEmpty()) {
                Province::query()->upsert(
                    $provinceRows->all(),
                    ['external_id'],
                    ['country_id', 'department_id', 'name', 'ubigeo_code', 'shipping_price', 'is_active', 'remote_updated_at', 'updated_at', 'created_at'],
                );
            }

            $provinceMap = Province::query()->pluck('id', 'external_id');

            $rows = collect($items)
                ->map(function (array $item) use ($countryMap, $departmentMap, $provinceMap): ?array {
                    $externalId = $this->resolveNumericExternalId($item['id'] ?? null);
                    $countryExternalId = $this->resolveNumericExternalId(
                        Arr::get($item, 'country.id') ?? Arr::get($item, 'department.country_id')
                    );
                    $departmentExternalId = $this->resolveNumericExternalId(
                        Arr::get($item, 'department.id') ?? Arr::get($item, 'province.department_id')
                    );
                    $provinceExternalId = $this->resolveNumericExternalId($item['province_id'] ?? Arr::get($item, 'province.id'));

                    if (
                        $externalId === null
                        || $countryExternalId === null
                        || $departmentExternalId === null
                        || $provinceExternalId === null
                    ) {
                        return null;
                    }

                    $countryId = $countryMap->get($countryExternalId);
                    $departmentId = $departmentMap->get($departmentExternalId);
                    $provinceId = $provinceMap->get($provinceExternalId);
                    if ($countryId === null || $departmentId === null || $provinceId === null) {
                        return null;
                    }

                    return [
                        'external_id' => $externalId,
                        'country_id' => (int) $countryId,
                        'department_id' => (int) $departmentId,
                        'province_id' => (int) $provinceId,
                        'name' => (string) ($item['name'] ?? ''),
                        'ubigeo_code' => Arr::get($item, 'ubigeo_code'),
                        'shipping_price' => $this->normalizeMoneyToMinorAmount(Arr::get($item, 'shipping_price')),
                        'has_delivery_express' => (bool) ($item['has_delivery_express'] ?? false),
                        'is_active' => (bool) ($item['is_active'] ?? true),
                        'remote_updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null),
                        'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                        'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
                        'deleted_at' => $this->normalizeTimestamp($item['deleted_at'] ?? null),
                    ];
                })
                ->filter()
                ->unique('external_id')
                ->values();

            if ($rows->isEmpty()) {
                return 0;
            }

            District::query()->upsert(
                $rows->all(),
                ['external_id'],
                ['country_id', 'department_id', 'province_id', 'name', 'ubigeo_code', 'shipping_price', 'has_delivery_express', 'is_active', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
            );

            return $rows->count();
        });
    }

    public function syncAddresses(): int
    {
        return $this->syncResource('addresses', function (array $items): int {
            $countries = [];
            $departments = [];
            $provinces = [];
            $districts = [];

            foreach ($items as $countryItem) {
                if (! is_array($countryItem)) {
                    continue;
                }

                $countryExternalId = $this->resolveNumericExternalId($countryItem['id'] ?? null);
                if ($countryExternalId === null) {
                    continue;
                }

                $countries[$countryExternalId] = [
                    'external_id' => $countryExternalId,
                    'name' => (string) ($countryItem['name'] ?? ''),
                    'iso_code_2' => Arr::get($countryItem, 'iso_code_2'),
                    'iso_code_3' => Arr::get($countryItem, 'iso_code_3'),
                    'is_active' => (bool) ($countryItem['is_active'] ?? true),
                    'remote_updated_at' => $this->normalizeTimestamp($countryItem['updated_at'] ?? null),
                    'updated_at' => $this->normalizeTimestamp($countryItem['updated_at'] ?? null) ?? now()->toDateTimeString(),
                    'created_at' => $this->normalizeTimestamp($countryItem['created_at'] ?? null) ?? now()->toDateTimeString(),
                    'deleted_at' => $this->normalizeTimestamp($countryItem['deleted_at'] ?? null),
                ];

                foreach ((array) Arr::get($countryItem, 'departments', []) as $departmentItem) {
                    if (! is_array($departmentItem)) {
                        continue;
                    }

                    $departmentExternalId = $this->resolveNumericExternalId($departmentItem['id'] ?? null);
                    if ($departmentExternalId === null) {
                        continue;
                    }

                    $departments[$departmentExternalId] = [
                        'external_id' => $departmentExternalId,
                        'country_external_id' => $countryExternalId,
                        'name' => (string) ($departmentItem['name'] ?? ''),
                        'ubigeo_code' => Arr::get($departmentItem, 'ubigeo_code'),
                        'remote_updated_at' => $this->normalizeTimestamp($departmentItem['updated_at'] ?? null),
                        'updated_at' => $this->normalizeTimestamp($departmentItem['updated_at'] ?? null) ?? now()->toDateTimeString(),
                        'created_at' => $this->normalizeTimestamp($departmentItem['created_at'] ?? null) ?? now()->toDateTimeString(),
                        'deleted_at' => $this->normalizeTimestamp($departmentItem['deleted_at'] ?? null),
                    ];

                    foreach ((array) Arr::get($departmentItem, 'provinces', []) as $provinceItem) {
                        if (! is_array($provinceItem)) {
                            continue;
                        }

                        $provinceExternalId = $this->resolveNumericExternalId($provinceItem['id'] ?? null);
                        if ($provinceExternalId === null) {
                            continue;
                        }

                        $provinces[$provinceExternalId] = [
                            'external_id' => $provinceExternalId,
                            'country_external_id' => $countryExternalId,
                            'department_external_id' => $departmentExternalId,
                            'name' => (string) ($provinceItem['name'] ?? ''),
                            'ubigeo_code' => Arr::get($provinceItem, 'ubigeo_code'),
                            'shipping_price' => $this->normalizeMoneyToMinorAmount(Arr::get($provinceItem, 'shipping_price')),
                            'is_active' => (bool) ($provinceItem['is_active'] ?? true),
                            'remote_updated_at' => $this->normalizeTimestamp($provinceItem['updated_at'] ?? null),
                            'updated_at' => $this->normalizeTimestamp($provinceItem['updated_at'] ?? null) ?? now()->toDateTimeString(),
                            'created_at' => $this->normalizeTimestamp($provinceItem['created_at'] ?? null) ?? now()->toDateTimeString(),
                            'deleted_at' => $this->normalizeTimestamp($provinceItem['deleted_at'] ?? null),
                        ];

                        foreach ((array) Arr::get($provinceItem, 'districts', []) as $districtItem) {
                            if (! is_array($districtItem)) {
                                continue;
                            }

                            $districtExternalId = $this->resolveNumericExternalId($districtItem['id'] ?? null);
                            if ($districtExternalId === null) {
                                continue;
                            }

                            $districts[$districtExternalId] = [
                                'external_id' => $districtExternalId,
                                'country_external_id' => $countryExternalId,
                                'department_external_id' => $departmentExternalId,
                                'province_external_id' => $provinceExternalId,
                                'name' => (string) ($districtItem['name'] ?? ''),
                                'ubigeo_code' => Arr::get($districtItem, 'ubigeo_code'),
                                'shipping_price' => $this->normalizeMoneyToMinorAmount(Arr::get($districtItem, 'shipping_price')),
                                'has_delivery_express' => (bool) ($districtItem['has_delivery_express'] ?? false),
                                'is_active' => (bool) ($districtItem['is_active'] ?? true),
                                'remote_updated_at' => $this->normalizeTimestamp($districtItem['updated_at'] ?? null),
                                'updated_at' => $this->normalizeTimestamp($districtItem['updated_at'] ?? null) ?? now()->toDateTimeString(),
                                'created_at' => $this->normalizeTimestamp($districtItem['created_at'] ?? null) ?? now()->toDateTimeString(),
                                'deleted_at' => $this->normalizeTimestamp($districtItem['deleted_at'] ?? null),
                            ];
                        }
                    }
                }
            }

            if ($countries === [] && $departments === [] && $provinces === [] && $districts === []) {
                return 0;
            }

            Country::query()->upsert(
                array_values($countries),
                ['external_id'],
                ['name', 'iso_code_2', 'iso_code_3', 'is_active', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
            );

            $countryMap = Country::query()->pluck('id', 'external_id');

            $departmentRows = collect($departments)
                ->map(function (array $department) use ($countryMap): ?array {
                    $countryId = $countryMap->get($department['country_external_id']);
                    if ($countryId === null) {
                        return null;
                    }

                    return Arr::except($department, ['country_external_id']) + ['country_id' => $countryId];
                })
                ->filter()
                ->values()
                ->all();

            Department::query()->upsert(
                $departmentRows,
                ['external_id'],
                ['country_id', 'name', 'ubigeo_code', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
            );

            $departmentMap = Department::query()->pluck('id', 'external_id');

            $provinceRows = collect($provinces)
                ->map(function (array $province) use ($countryMap, $departmentMap): ?array {
                    $countryId = $countryMap->get($province['country_external_id']);
                    $departmentId = $departmentMap->get($province['department_external_id']);
                    if ($countryId === null || $departmentId === null) {
                        return null;
                    }

                    return Arr::except($province, ['country_external_id', 'department_external_id']) + [
                        'country_id' => $countryId,
                        'department_id' => $departmentId,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            Province::query()->upsert(
                $provinceRows,
                ['external_id'],
                ['country_id', 'department_id', 'name', 'ubigeo_code', 'shipping_price', 'is_active', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
            );

            $provinceMap = Province::query()->pluck('id', 'external_id');

            $districtRows = collect($districts)
                ->map(function (array $district) use ($countryMap, $departmentMap, $provinceMap): ?array {
                    $countryId = $countryMap->get($district['country_external_id']);
                    $departmentId = $departmentMap->get($district['department_external_id']);
                    $provinceId = $provinceMap->get($district['province_external_id']);
                    if ($countryId === null || $departmentId === null || $provinceId === null) {
                        return null;
                    }

                    return Arr::except($district, ['country_external_id', 'department_external_id', 'province_external_id']) + [
                        'country_id' => $countryId,
                        'department_id' => $departmentId,
                        'province_id' => $provinceId,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            District::query()->upsert(
                $districtRows,
                ['external_id'],
                ['country_id', 'department_id', 'province_id', 'name', 'ubigeo_code', 'shipping_price', 'has_delivery_express', 'is_active', 'remote_updated_at', 'updated_at', 'created_at', 'deleted_at'],
            );

            return count($countries) + count($departments) + count($provinces) + count($districts);
        });
    }

    /**
     * @param  callable(array<int, array<string, mixed>>): int|callable(array<int, array<string, mixed>>, string): int  $syncer
     */
    protected function syncResource(
        string $resource,
        callable $syncer,
        bool $allowMissing = false,
        bool $useCheckpoint = true,
        ?callable $afterToken = null
    ): int {
        $syncRun = SyncRun::query()->create([
            'resource' => $resource,
            'status' => 'running',
            'started_at' => now(),
            'records_processed' => 0,
            'errors_count' => 0,
        ]);

        $processed = 0;
        $checkpoint = $useCheckpoint ? $this->resolveCheckpoint($resource) : null;
        $tokens = $this->resolveIntegrationTokens();

        try {
            foreach ($tokens as $token) {
                $cursor = null;

                do {
                    $response = $this->client->fetch(
                        resource: $resource,
                        updatedSince: $checkpoint?->toIso8601String(),
                        cursor: $cursor,
                        allowMissing: $allowMissing,
                        token: $token
                    );
                    if ($response === null) {
                        break;
                    }
                    $items = $response['data'];

                    DB::transaction(function () use ($syncer, $items, $token, &$processed): void {
                        $processed += $this->invokeSyncer($syncer, $items, $token);
                    });

                    $cursor = Arr::get($response, 'meta.next_cursor');
                } while ($cursor !== null);

                if ($afterToken !== null) {
                    $afterToken($token);
                }
            }

            $syncRun->update([
                'status' => 'completed',
                'finished_at' => now(),
                'records_processed' => $processed,
                'checkpoint_updated_since' => now(),
            ]);
        } catch (\Throwable $throwable) {
            $syncRun->update([
                'status' => 'failed',
                'finished_at' => now(),
                'records_processed' => $processed,
                'errors_count' => 1,
                'meta' => ['error' => $throwable->getMessage()],
            ]);

            throw $throwable;
        }

        return $processed;
    }

    /**
     * @param  callable(array<int, array<string, mixed>>): int|callable(array<int, array<string, mixed>>, string): int  $syncer
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function invokeSyncer(callable $syncer, array $items, string $token): int
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($syncer));

        if ($reflection->getNumberOfParameters() >= 2) {
            return $syncer($items, $token);
        }

        return $syncer($items);
    }

    protected function resolveCheckpoint(string $resource): ?CarbonImmutable
    {
        $lastRun = SyncRun::query()
            ->where('resource', $resource)
            ->where('status', 'completed')
            ->whereNotNull('checkpoint_updated_since')
            ->latest('checkpoint_updated_since')
            ->first();

        if (! $lastRun?->checkpoint_updated_since) {
            return null;
        }

        return CarbonImmutable::parse($lastRun->checkpoint_updated_since);
    }

    protected function syncCategoryResource(
        string $resource,
        string $parentReferenceField,
        ?CarbonImmutable $checkpoint,
        bool $allowMissing
    ): int {
        $processed = 0;
        $tokens = $this->resolveIntegrationTokens();

        foreach ($tokens as $token) {
            $cursor = null;

            do {
                $response = $this->client->fetch(
                    resource: $resource,
                    updatedSince: $checkpoint?->toIso8601String(),
                    cursor: $cursor,
                    allowMissing: $allowMissing,
                    token: $token,
                );

                if ($response === null) {
                    break;
                }

                $items = $response['data'];

                DB::transaction(function () use ($items, $parentReferenceField, &$processed): void {
                    $processed += $this->upsertCategories($items, $parentReferenceField);
                });

                $cursor = Arr::get($response, 'meta.next_cursor');
            } while ($cursor !== null);
        }

        return $processed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function upsertCategories(array $items, string $parentReferenceField): int
    {
        $rows = collect($items)
            ->map(fn (array $item): array => [
                'external_id' => (int) $item['id'],
                'name' => (string) ($item['name'] ?? ''),
                'slug' => $item['slug'] ?? null,
                'is_active' => (bool) ($item['is_active'] ?? true),
                'deleted_at' => $this->normalizeTimestamp($item['deleted_at'] ?? null),
                'updated_at' => $this->normalizeTimestamp($item['updated_at'] ?? null) ?? now()->toDateTimeString(),
                'created_at' => $this->normalizeTimestamp($item['created_at'] ?? null) ?? now()->toDateTimeString(),
            ])
            ->values();

        if ($rows->isEmpty()) {
            return 0;
        }

        Category::query()->upsert(
            $rows->all(),
            ['external_id'],
            ['name', 'slug', 'is_active', 'deleted_at', 'updated_at', 'created_at'],
        );

        $externalIds = collect($items)
            ->flatMap(fn (array $item): array => [
                (int) $item['id'],
                (int) ($item[$parentReferenceField] ?? 0),
            ])
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $categories = Category::query()
            ->whereIn('external_id', $externalIds)
            ->get(['id', 'external_id'])
            ->keyBy('external_id');

        foreach ($items as $item) {
            $category = $categories->get((int) $item['id']);
            if (! $category) {
                continue;
            }

            $parentExternalId = Arr::get($item, $parentReferenceField);
            $parentId = $parentExternalId !== null
                ? optional($categories->get((int) $parentExternalId))->id
                : null;

            $category->update(['parent_id' => $parentId]);
        }

        return $rows->count();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, int>  $brandBySlug
     * @param  Collection<string, int>  $brandByName
     * @param  Collection<string, int>  $brandByNameLower
     */
    protected function resolveBrandIdFromNestedPayload(array $item, Collection $brandBySlug, Collection $brandByName, Collection $brandByNameLower): ?int
    {
        $brand = Arr::get($item, 'brand');
        if (! is_array($brand)) {
            return null;
        }

        $slug = (string) Arr::get($brand, 'slug', '');
        if ($slug !== '') {
            $found = $brandBySlug->get($slug);
            if ($found !== null) {
                return (int) $found;
            }
        }

        $name = (string) Arr::get($brand, 'name', '');
        if ($name !== '') {
            $found = $brandByName->get($name);
            if ($found !== null) {
                return (int) $found;
            }

            $foundLower = $brandByNameLower->get(Str::lower($name));
            if ($foundLower !== null) {
                return (int) $foundLower;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, int>  $categoryBySlug
     * @param  Collection<string, int>  $categoryByName
     * @param  Collection<string, int>  $categoryByNameLower
     */
    protected function resolveSubcategoryIdFromNestedPayload(
        array $item,
        Collection $categoryBySlug,
        Collection $categoryByName,
        Collection $categoryByNameLower,
        ?int $parentCategoryId = null
    ): ?int {
        $subcategory = Arr::get($item, 'subcategory');
        if (! is_array($subcategory)) {
            return null;
        }

        $slug = (string) Arr::get($subcategory, 'slug', '');
        $name = (string) Arr::get($subcategory, 'name', '');

        if ($slug === '' && $name !== '') {
            $slug = Str::slug($name);
        }

        if ($slug !== '') {
            $found = $categoryBySlug->get($slug);
            if ($found !== null) {
                if ($parentCategoryId !== null) {
                    Category::query()->whereKey($found)->update(['parent_id' => $parentCategoryId]);
                }

                return (int) $found;
            }

            $foundBySlug = Category::query()->where('slug', $slug)->first();
            if ($foundBySlug) {
                if ($parentCategoryId !== null) {
                    $foundBySlug->update(['parent_id' => $parentCategoryId]);
                }

                return (int) $foundBySlug->id;
            }
        }

        if ($name !== '') {
            $found = $categoryByName->get($name);
            if ($found !== null) {
                if ($parentCategoryId !== null) {
                    Category::query()->whereKey($found)->update(['parent_id' => $parentCategoryId]);
                }

                return (int) $found;
            }

            $foundLower = $categoryByNameLower->get(Str::lower($name));
            if ($foundLower !== null) {
                if ($parentCategoryId !== null) {
                    Category::query()->whereKey($foundLower)->update(['parent_id' => $parentCategoryId]);
                }

                return (int) $foundLower;
            }

            $foundByName = Category::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                ->first();
            if ($foundByName) {
                if ($parentCategoryId !== null) {
                    $foundByName->update(['parent_id' => $parentCategoryId]);
                }

                return (int) $foundByName->id;
            }
        }

        if ($slug === '' && $name === '') {
            return null;
        }

        $created = Category::query()->create([
            'external_id' => null,
            'name' => $name !== '' ? $name : (string) Str::title(str_replace('-', ' ', $slug)),
            'slug' => $slug !== '' ? $slug : null,
            'is_active' => true,
            'parent_id' => $parentCategoryId,
        ]);

        return (int) $created->id;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveCategoryIdFromNestedPayload(array $item): ?int
    {
        $category = Arr::get($item, 'category');
        if (! is_array($category)) {
            return null;
        }

        $slug = (string) Arr::get($category, 'slug', '');
        $name = (string) Arr::get($category, 'name', '');

        if ($slug === '' && $name !== '') {
            $slug = Str::slug($name);
        }

        if ($slug !== '') {
            $foundBySlug = Category::query()->where('slug', $slug)->first();
            if ($foundBySlug) {
                return (int) $foundBySlug->id;
            }
        }

        if ($name !== '') {
            $foundByName = Category::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                ->first();
            if ($foundByName) {
                return (int) $foundByName->id;
            }
        }

        if ($slug === '' && $name === '') {
            return null;
        }

        $created = Category::query()->create([
            'external_id' => null,
            'name' => $name !== '' ? $name : (string) Str::title(str_replace('-', ' ', $slug)),
            'slug' => $slug !== '' ? $slug : null,
            'is_active' => true,
            'parent_id' => null,
        ]);

        return (int) $created->id;
    }

    protected function resolveNumericExternalId(mixed $id): ?int
    {
        if (! is_numeric($id)) {
            return null;
        }

        $numericId = (int) $id;

        return $numericId > 0 ? $numericId : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveExternalRef(array $item, string $fallbackKey): string
    {
        $numericId = $this->resolveNumericExternalId(Arr::get($item, 'id'));
        if ($numericId !== null) {
            return "id:{$numericId}";
        }

        return 'h:'.hash('sha256', $fallbackKey);
    }

    protected function resolveImageExternalRef(string $ownerRef, string $url, int $sortOrder): string
    {
        return 'img:'.hash('sha256', "{$ownerRef}|{$sortOrder}|{$url}");
    }

    /**
     * @return list<string>
     */
    protected function normalizeUrlList(mixed $images): array
    {
        if (! is_array($images)) {
            return [];
        }

        /** @var list<string> $urls */
        $urls = collect($images)
            ->filter(fn (mixed $url): bool => is_string($url) && trim($url) !== '')
            ->map(fn (string $url): string => trim($url))
            ->values()
            ->all();

        return $urls;
    }

    /**
     * @param  array<string, mixed>  $variant
     * @return list<string>
     */
    protected function normalizeVariantImageList(array $variant): array
    {
        $urls = [];

        $primary = Arr::get($variant, 'image');
        if (is_string($primary) && trim($primary) !== '') {
            $urls[] = trim($primary);
        }

        foreach ($this->normalizeUrlList(Arr::get($variant, 'images', [])) as $image) {
            $urls[] = $image;
        }

        /** @var list<string> $uniqueUrls */
        $uniqueUrls = array_values(array_unique($urls));

        return $uniqueUrls;
    }

    protected function normalizeTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeMoneyToMinorAmount(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_int($amount)) {
            return max(0, $amount);
        }

        if (is_float($amount)) {
            return max(0, (int) round($amount * 100));
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string) $amount);
        if (! is_numeric($normalized)) {
            return 0;
        }

        if (str_contains($normalized, '.')) {
            return max(0, (int) round(((float) $normalized) * 100));
        }

        return max(0, (int) $normalized);
    }

    /**
     * @param  list<int>  $scopedBrandIds
     * @param  list<string>  $seenProductRefs
     * @param  list<string>  $seenVariantRefs
     * @param  list<string>  $seenImageRefs
     */
    protected function softDeleteMissingCatalogRecords(
        array $scopedBrandIds,
        array $seenProductRefs,
        array $seenVariantRefs,
        array $seenImageRefs
    ): void {
        if ($scopedBrandIds === []) {
            return;
        }

        $scopedProductIds = Product::query()->whereIn('brand_id', $scopedBrandIds)->pluck('id');

        $productQuery = Product::query()
            ->whereIn('brand_id', $scopedBrandIds)
            ->whereNull('deleted_at');
        if ($seenProductRefs !== []) {
            $productQuery->whereNotIn('external_ref', $seenProductRefs);
        }
        $productQuery->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        $variantQuery = ProductVariant::query()
            ->whereIn('product_id', $scopedProductIds)
            ->whereNull('deleted_at');
        if ($seenVariantRefs !== []) {
            $variantQuery->whereNotIn('external_ref', $seenVariantRefs);
        }
        $variantQuery->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        $imageQuery = ProductImage::query()
            ->whereIn('product_id', $scopedProductIds)
            ->whereNull('deleted_at');
        if ($seenImageRefs !== []) {
            $imageQuery->whereNotIn('external_ref', $seenImageRefs);
        }
        $imageQuery->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, list<int>>
     */
    protected function resolveTokenBrandScopes(): array
    {
        $scopes = [];

        BrandWhitelist::query()
            ->where('enabled', true)
            ->whereNotNull('main_store_token')
            ->get(['brand_id', 'main_store_token'])
            ->each(function (BrandWhitelist $whitelist) use (&$scopes): void {
                $token = trim((string) $whitelist->main_store_token);
                if ($token === '') {
                    return;
                }

                $scopes[$token] ??= [];
                $scopes[$token][] = (int) $whitelist->brand_id;
            });

        foreach ($scopes as $token => $brandIds) {
            $scopes[$token] = array_values(array_unique($brandIds));
        }

        return $scopes;
    }

    /**
     * @return list<string>
     */
    protected function resolveIntegrationTokens(): array
    {
        /** @var Collection<int, string> $tokens */
        $tokens = BrandWhitelist::query()
            ->where('enabled', true)
            ->whereNotNull('main_store_token')
            ->get(['main_store_token'])
            ->pluck('main_store_token')
            ->filter(fn (?string $token): bool => $token !== null && trim($token) !== '')
            ->map(fn (string $token): string => trim($token))
            ->unique()
            ->values();

        if ($tokens->isNotEmpty()) {
            return $tokens->all();
        }

        $fallbackToken = trim((string) config('services.main_store.token', ''));
        if ($fallbackToken !== '') {
            return [$fallbackToken];
        }

        throw new RuntimeException('No main store token configured. Save a token in Admin > Brands for at least one enabled brand.');
    }
}
