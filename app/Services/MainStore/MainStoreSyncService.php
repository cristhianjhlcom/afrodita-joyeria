<?php

namespace App\Services\MainStore;

use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
                        'deleted_at' => $item['deleted_at'] ?? null,
                        'updated_at' => $item['updated_at'] ?? now(),
                        'created_at' => $item['created_at'] ?? now(),
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
        return $this->syncResource('categories', function (array $items): int {
            $rows = collect($items)
                ->map(fn (array $item): array => [
                    'external_id' => (int) $item['id'],
                    'name' => (string) ($item['name'] ?? ''),
                    'slug' => $item['slug'] ?? null,
                    'is_active' => (bool) ($item['is_active'] ?? true),
                    'deleted_at' => $item['deleted_at'] ?? null,
                    'updated_at' => $item['updated_at'] ?? now(),
                    'created_at' => $item['created_at'] ?? now(),
                ])
                ->values();

            Category::query()->upsert(
                $rows->all(),
                ['external_id'],
                ['name', 'slug', 'is_active', 'deleted_at', 'updated_at', 'created_at'],
            );

            $categories = Category::query()
                ->whereIn('external_id', $rows->pluck('external_id'))
                ->get(['id', 'external_id'])
                ->keyBy('external_id');

            foreach ($items as $item) {
                $category = $categories->get((int) $item['id']);
                if (! $category) {
                    continue;
                }

                $parentExternalId = Arr::get($item, 'parent_id');
                $parentId = $parentExternalId !== null
                    ? optional($categories->get((int) $parentExternalId))->id
                    : null;

                $category->update(['parent_id' => $parentId]);
            }

            return $rows->count();
        });
    }

    public function syncProducts(): int
    {
        return $this->syncResource('products', function (array $items): int {
            $brandMap = Brand::query()->pluck('id', 'external_id');
            $categoryMap = Category::query()->pluck('id', 'external_id');
            $whitelistedBrandIds = BrandWhitelist::query()
                ->where('enabled', true)
                ->pluck('brand_id')
                ->all();

            $rows = collect($items)
                ->map(function (array $item) use ($brandMap, $categoryMap): ?array {
                    $brandId = $brandMap->get((int) ($item['brand_id'] ?? 0));
                    $subcategoryId = $categoryMap->get((int) ($item['subcategory_id'] ?? 0));

                    if ($brandId === null || $subcategoryId === null) {
                        return null;
                    }

                    return [
                        'external_id' => (int) $item['id'],
                        'brand_id' => $brandId,
                        'subcategory_id' => $subcategoryId,
                        'name' => (string) ($item['name'] ?? ''),
                        'slug' => (string) ($item['slug'] ?? "product-{$item['id']}"),
                        'description' => $item['description'] ?? null,
                        'status' => (string) ($item['status'] ?? Product::STATUS_DRAFT),
                        'deleted_at' => $item['deleted_at'] ?? null,
                        'updated_at' => $item['updated_at'] ?? now(),
                        'created_at' => $item['created_at'] ?? now(),
                    ];
                })
                ->filter()
                ->filter(fn (array $row): bool => in_array((int) $row['brand_id'], $whitelistedBrandIds, true))
                ->values();

            if ($rows->isEmpty()) {
                return 0;
            }

            Product::query()->upsert(
                $rows->all(),
                ['external_id'],
                ['brand_id', 'subcategory_id', 'name', 'slug', 'description', 'status', 'deleted_at', 'updated_at', 'created_at'],
            );

            return $rows->count();
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

                    return [
                        'external_id' => (int) $item['id'],
                        'product_id' => $productId,
                        'sku' => $item['sku'] ?? null,
                        'code' => $item['code'] ?? null,
                        'price' => Arr::get($item, 'price'),
                        'sale_price' => Arr::get($item, 'sale_price'),
                        'color' => $item['color'] ?? null,
                        'hex' => $item['hex'] ?? null,
                        'size' => $item['size'] ?? null,
                        'updated_at' => $item['updated_at'] ?? now(),
                        'created_at' => $item['created_at'] ?? now(),
                        'deleted_at' => $item['deleted_at'] ?? null,
                    ];
                })
                ->filter()
                ->values();

            if ($rows->isEmpty()) {
                return 0;
            }

            ProductVariant::query()->upsert(
                $rows->all(),
                ['external_id'],
                ['product_id', 'sku', 'code', 'price', 'sale_price', 'color', 'hex', 'size', 'updated_at', 'created_at', 'deleted_at'],
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
                        'external_id' => (int) ($item['id'] ?? 0),
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'url' => (string) ($item['url'] ?? ''),
                        'sort_order' => (int) ($item['sort_order'] ?? 0),
                        'alt' => $item['alt'] ?? null,
                        'is_primary' => (bool) ($item['is_primary'] ?? false),
                        'updated_at' => $item['updated_at'] ?? now(),
                        'created_at' => $item['created_at'] ?? now(),
                        'deleted_at' => $item['deleted_at'] ?? null,
                    ];
                })
                ->filter(fn (?array $row): bool => $row !== null && $row['url'] !== '')
                ->values();

            if ($rows->isEmpty()) {
                return 0;
            }

            ProductImage::query()->upsert(
                $rows->all(),
                ['external_id'],
                ['product_id', 'product_variant_id', 'url', 'sort_order', 'alt', 'is_primary', 'updated_at', 'created_at', 'deleted_at'],
            );

            return $rows->count();
        });
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
                    'updated_at' => $item['updated_at'] ?? now(),
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
                    'placed_at' => $item['placed_at'] ?? null,
                    'updated_at' => $item['updated_at'] ?? now(),
                    'created_at' => $item['created_at'] ?? now(),
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

    /**
     * @param  callable(array<int, array<string, mixed>>): int  $syncer
     */
    protected function syncResource(string $resource, callable $syncer): int
    {
        $syncRun = SyncRun::query()->create([
            'resource' => $resource,
            'status' => 'running',
            'started_at' => now(),
            'records_processed' => 0,
            'errors_count' => 0,
        ]);

        $processed = 0;
        $cursor = null;
        $checkpoint = $this->resolveCheckpoint($resource);

        try {
            do {
                $response = $this->client->fetch($resource, $checkpoint?->toIso8601String(), $cursor);
                $items = $response['data'];

                DB::transaction(function () use ($syncer, $items, &$processed): void {
                    $processed += $syncer($items);
                });

                $cursor = Arr::get($response, 'meta.next_cursor');
            } while ($cursor !== null);

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
}
