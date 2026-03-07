<?php

namespace App\Services\Storefront;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CartService
{
    public const SESSION_KEY = 'storefront.cart.items';

    /**
     * @return array{items_count: int, distinct_items: int, subtotal_minor: int, currency: string}
     */
    public function summary(): array
    {
        $items = $this->detailedItems();

        return [
            'items_count' => $items->sum(fn (array $item): int => $item['quantity']),
            'distinct_items' => $items->count(),
            'subtotal_minor' => $items->sum(fn (array $item): int => $item['line_total']),
            'currency' => (string) config('services.main_store.currency', 'PEN'),
        ];
    }

    /**
     * @return Collection<int, array{
     *     variant_id: int,
     *     quantity: int,
     *     variant: ProductVariant,
     *     unit_price: int,
     *     line_total: int,
     *     stock_available: int
     * }>
     */
    public function detailedItems(): Collection
    {
        $rows = $this->readRows();

        if ($rows === []) {
            return collect();
        }

        $variants = ProductVariant::query()
            ->whereIn('id', array_keys($rows))
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->with([
                'product:id,name,slug,featured_image',
            ])
            ->get([
                'id',
                'product_id',
                'external_id',
                'sku',
                'code',
                'price',
                'sale_price',
                'color',
                'size',
                'stock_available',
                'is_active',
            ])
            ->keyBy('id');

        $validRows = [];
        $items = collect();

        foreach ($rows as $variantId => $quantity) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($variantId);

            if (! $variant) {
                continue;
            }

            $validRows[$variantId] = $quantity;
            $unitPrice = (int) ($variant->sale_price ?? $variant->price ?? 0);

            $items->push([
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'variant' => $variant,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
                'stock_available' => (int) $variant->stock_available,
            ]);
        }

        if ($validRows !== $rows) {
            $this->writeRows($validRows);
        }

        return $items;
    }

    /**
     * @return array{ok: bool, message: string, code: string}
     */
    public function addVariant(int $variantId, int $quantity = 1): array
    {
        if ($quantity < 1) {
            return $this->result(false, 'invalid_quantity', __('Invalid quantity.'));
        }

        $variant = $this->findVariant($variantId);

        if (! $variant) {
            return $this->result(false, 'not_found', __('Variant is no longer available.'));
        }

        $stockAvailable = (int) $variant->stock_available;

        if ($stockAvailable <= 0) {
            return $this->result(false, 'out_of_stock', __('This variant is out of stock.'));
        }

        $rows = $this->readRows();
        $currentQuantity = (int) ($rows[$variantId] ?? 0);
        $nextQuantity = $currentQuantity + $quantity;

        if ($nextQuantity > $stockAvailable) {
            return $this->result(false, 'stock_limit', __('You reached the maximum available stock for this item.'));
        }

        $rows[$variantId] = $nextQuantity;
        $this->writeRows($rows);

        return $this->result(true, $currentQuantity > 0 ? 'updated' : 'added', __('Product added to cart.'));
    }

    /**
     * @return array{ok: bool, message: string, code: string}
     */
    public function setQuantity(int $variantId, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->remove($variantId);
        }

        $variant = $this->findVariant($variantId);

        if (! $variant) {
            return $this->result(false, 'not_found', __('Variant is no longer available.'));
        }

        $stockAvailable = (int) $variant->stock_available;
        if ($quantity > $stockAvailable) {
            return $this->result(false, 'stock_limit', __('You reached the maximum available stock for this item.'));
        }

        $rows = $this->readRows();
        $rows[$variantId] = $quantity;
        $this->writeRows($rows);

        return $this->result(true, 'updated', __('Cart updated.'));
    }

    /**
     * @return array{ok: bool, message: string, code: string}
     */
    public function increment(int $variantId): array
    {
        return $this->addVariant($variantId, 1);
    }

    /**
     * @return array{ok: bool, message: string, code: string}
     */
    public function decrement(int $variantId): array
    {
        $rows = $this->readRows();
        $currentQuantity = (int) ($rows[$variantId] ?? 0);

        if ($currentQuantity <= 1) {
            return $this->remove($variantId);
        }

        return $this->setQuantity($variantId, $currentQuantity - 1);
    }

    /**
     * @return array{ok: bool, message: string, code: string}
     */
    public function remove(int $variantId): array
    {
        $rows = $this->readRows();

        if (! array_key_exists($variantId, $rows)) {
            return $this->result(false, 'not_found', __('Item is not in your cart.'));
        }

        unset($rows[$variantId]);
        $this->writeRows($rows);

        return $this->result(true, 'removed', __('Item removed from cart.'));
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * @return array{ok: bool, message: string, code: string, order_id?: int}
     */
    public function processPurchase(): array
    {
        $items = $this->detailedItems();

        if ($items->isEmpty()) {
            return $this->result(false, 'empty_cart', __('Your cart is empty.'));
        }

        foreach ($items as $item) {
            if ((int) $item['stock_available'] < (int) $item['quantity']) {
                return $this->result(false, 'stock_limit', __('Some items exceed the available stock.'));
            }
        }

        $subtotal = $items->sum(fn (array $item): int => (int) $item['line_total']);

        $order = DB::transaction(function () use ($items, $subtotal): Order {
            $order = Order::query()->create([
                'status' => 'pending',
                'currency' => (string) config('services.main_store.currency', 'PEN'),
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'grand_total' => $subtotal,
                'placed_at' => now(),
            ]);

            $orderRows = $items->map(function (array $item) use ($order): array {
                /** @var ProductVariant $variant */
                $variant = $item['variant'];
                $productName = (string) ($variant->product?->name ?? __('Product'));
                $variantSuffix = trim(collect([
                    $variant->size ? __('Size :size', ['size' => $variant->size]) : null,
                    $variant->color ? __('Color :color', ['color' => $variant->color]) : null,
                ])->filter()->implode(' | '));

                $nameSnapshot = $variantSuffix !== '' ? "{$productName} ({$variantSuffix})" : $productName;

                return [
                    'order_id' => $order->id,
                    'variant_external_id' => $variant->external_id,
                    'sku' => $variant->sku ?? $variant->code,
                    'name_snapshot' => $nameSnapshot,
                    'qty' => (int) $item['quantity'],
                    'unit_price' => (int) $item['unit_price'],
                    'line_total' => (int) $item['line_total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            if ($orderRows !== []) {
                OrderItem::query()->insert($orderRows);
            }

            return $order;
        });

        $this->clear();

        return [
            'ok' => true,
            'code' => 'checkout_processed',
            'message' => __('Purchase processed successfully.'),
            'order_id' => (int) $order->id,
        ];
    }

    /**
     * @return array<int, int>
     */
    protected function readRows(): array
    {
        $rows = Session::get(self::SESSION_KEY, []);

        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];

        foreach ($rows as $variantId => $quantity) {
            if (! is_numeric($variantId) || ! is_numeric($quantity)) {
                continue;
            }

            $resolvedVariantId = (int) $variantId;
            $resolvedQuantity = (int) $quantity;

            if ($resolvedVariantId <= 0 || $resolvedQuantity <= 0) {
                continue;
            }

            $normalized[$resolvedVariantId] = $resolvedQuantity;
        }

        return $normalized;
    }

    /**
     * @param  array<int, int>  $rows
     */
    protected function writeRows(array $rows): void
    {
        if ($rows === []) {
            $this->clear();

            return;
        }

        Session::put(self::SESSION_KEY, $rows);
    }

    protected function findVariant(int $variantId): ?ProductVariant
    {
        return ProductVariant::query()
            ->where('id', $variantId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->first([
                'id',
                'external_id',
                'product_id',
                'sku',
                'code',
                'price',
                'sale_price',
                'color',
                'size',
                'stock_available',
                'is_active',
            ]);
    }

    /**
     * @return array{ok: bool, message: string, code: string}
     */
    protected function result(bool $ok, string $code, string $message): array
    {
        return [
            'ok' => $ok,
            'code' => $code,
            'message' => $message,
        ];
    }
}
