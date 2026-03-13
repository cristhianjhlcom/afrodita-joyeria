<?php

namespace App\Services\Storefront;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\MainStore\MainStoreApiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;

class OrderPushService
{
    public function __construct(protected MainStoreApiClient $client) {}

    /**
     * @return array{ok: bool, code: string, message: string, response?: array<string, mixed>, remote_id?: int}
     */
    public function push(Order $order): array
    {
        $order->loadMissing('items');

        $externalOrderId = (string) ($order->main_store_external_order_id ?: $order->order_token);

        if ($externalOrderId === '') {
            return [
                'ok' => false,
                'code' => 'missing_external_order_id',
                'message' => __('External order id is required to push this order.'),
            ];
        }

        if ($order->main_store_external_order_id === null && $order->order_token) {
            $order->update(['main_store_external_order_id' => $order->order_token]);
        }

        $payload = $this->buildPayload($order, $externalOrderId);

        try {
            $response = $this->client->createOrder($payload);

            return [
                'ok' => true,
                'code' => 'order_pushed',
                'message' => __('Order sent to main store.'),
                'response' => $response,
                'remote_id' => $this->resolveRemoteId($response),
            ];
        } catch (RequestException $exception) {
            $responsePayload = $exception->response?->json() ?? [];
            $responseMessage = (string) Arr::get($responsePayload, 'error.message', $exception->getMessage());

            return [
                'ok' => false,
                'code' => (string) Arr::get($responsePayload, 'error.code', 'order_push_failed'),
                'message' => $responseMessage,
                'response' => is_array($responsePayload) ? $responsePayload : [],
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'code' => 'order_push_failed',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array{ok: bool, code: string, message: string, response?: array<string, mixed>, remote_id?: int}  $result
     */
    public function applyPushResult(Order $order, array $result, bool $incrementAttempts = true): void
    {
        $attempts = (int) $order->push_attempts;

        if ($incrementAttempts) {
            $attempts++;
        }

        $updates = [
            'push_status' => $result['ok'] ? 'pushed' : 'failed',
            'push_attempts' => $attempts,
            'push_last_error' => $result['ok'] ? null : (string) $result['message'],
            'push_last_response' => $result['response'] ?? null,
        ];

        if ($result['ok'] && isset($result['remote_id'])) {
            $updates['main_store_order_id'] = (int) $result['remote_id'];
        }

        $order->update($updates);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(Order $order, string $externalOrderId): array
    {
        $fullName = trim((string) $order->customer_name);
        $nameParts = $fullName !== '' ? (preg_split('/\s+/', $fullName) ?: []) : [];
        $firstName = $nameParts !== [] ? (string) array_shift($nameParts) : '';
        $lastName = $nameParts !== [] ? trim(implode(' ', $nameParts)) : '';

        return [
            'external_order_id' => $externalOrderId,
            'currency' => (string) $order->currency,
            'discount_amount' => (int) $order->discount_total,
            'shipping_cost' => (int) $order->shipping_total,
            'order_status' => (string) $order->status,
            'payment_status' => (string) $order->payment_status,
            'customer' => [
                'email' => $order->customer_email,
                'contact' => $fullName !== '' ? $fullName : $order->customer_email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $order->customer_phone,
                'doc_type' => $order->customer_document_type,
                'doc_number' => $order->customer_document_number,
                'observation' => $order->shipping_reference,
            ],
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'sku' => $item->sku,
                'quantity' => (int) $item->qty,
                'price_per_unit' => (int) $item->unit_price,
            ])->all(),
        ];
    }

    protected function resolveRemoteId(array $response): ?int
    {
        $remoteId = Arr::get($response, 'data.id');

        if (is_numeric($remoteId)) {
            return (int) $remoteId;
        }

        return null;
    }
}
