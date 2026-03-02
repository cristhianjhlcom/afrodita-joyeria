<?php

namespace App\Services\MainStore;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MainStoreOrderPushService
{
    public function __construct(protected MainStoreApiClient $client) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRemoteOrder(array $payload): Order
    {
        $externalOrderId = (string) Arr::get($payload, 'external_order_id', '');
        if ($externalOrderId === '') {
            throw new InvalidArgumentException('The payload must include external_order_id.');
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = Arr::get($payload, 'items', []);
        if ($items === []) {
            throw new InvalidArgumentException('The payload must include at least one item.');
        }

        $responsePayload = $this->client->createOrder($payload);
        $remoteData = is_array($responsePayload['data'] ?? null) ? $responsePayload['data'] : [];

        return DB::transaction(function () use ($externalOrderId, $items, $remoteData): Order {
            $subtotal = collect($items)->sum(fn (array $item): int => (int) ($item['quantity'] ?? 0) * (int) ($item['price_per_unit'] ?? 0));

            $order = Order::query()->firstOrNew([
                'main_store_external_order_id' => $externalOrderId,
            ]);

            if ($order->external_id === null) {
                $remoteNumericId = Arr::get($remoteData, 'id');
                if (is_numeric($remoteNumericId)) {
                    $order->external_id = (int) $remoteNumericId;
                }
            }

            $order->status = (string) Arr::get($remoteData, 'status', $order->status ?? 'pending');
            $order->currency = (string) Arr::get($remoteData, 'currency', $order->currency ?? 'USD');
            $order->subtotal = $subtotal;
            $order->discount_total = (int) Arr::get($remoteData, 'discount_total', $order->discount_total ?? 0);
            $order->shipping_total = (int) Arr::get($remoteData, 'shipping_total', $order->shipping_total ?? 0);
            $order->tax_total = (int) Arr::get($remoteData, 'tax_total', $order->tax_total ?? 0);
            $order->grand_total = (int) Arr::get($remoteData, 'grand_total', $order->grand_total ?? $subtotal);
            $order->placed_at = Arr::get($remoteData, 'placed_at', $order->placed_at ?? now());
            $order->save();

            OrderItem::query()->where('order_id', $order->id)->delete();

            $rows = collect($items)
                ->map(fn (array $item): array => [
                    'order_id' => $order->id,
                    'variant_external_id' => null,
                    'sku' => Arr::get($item, 'sku'),
                    'name_snapshot' => (string) Arr::get($item, 'name_snapshot', Arr::get($item, 'sku', 'Unknown item')),
                    'qty' => (int) Arr::get($item, 'quantity', 1),
                    'unit_price' => (int) Arr::get($item, 'price_per_unit', 0),
                    'line_total' => (int) Arr::get($item, 'quantity', 1) * (int) Arr::get($item, 'price_per_unit', 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($rows !== []) {
                OrderItem::query()->insert($rows);
            }

            return $order->refresh();
        });
    }

    public function cancelRemoteOrder(string $externalOrderId, string $note, bool $markRefunded): ?Order
    {
        $this->client->cancelOrder($externalOrderId, $note, $markRefunded);

        $order = Order::query()
            ->where('main_store_external_order_id', $externalOrderId)
            ->first();

        if (! $order && ctype_digit($externalOrderId)) {
            $order = Order::query()->where('external_id', (int) $externalOrderId)->first();
        }

        if (! $order) {
            return null;
        }

        $order->update([
            'status' => 'cancelled',
            'cancellation_note' => $note,
            'is_refunded' => $markRefunded,
        ]);

        return $order->refresh();
    }
}
