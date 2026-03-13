<?php

namespace App\Services\Storefront;

use App\Jobs\PushOrderToMainStoreJob;
use App\Models\District;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\User;
use App\Services\Payments\CulqiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CheckoutService
{
    public const SESSION_KEY = 'storefront.checkout.sessions';

    public function __construct(
        protected CartService $cartService,
        protected CulqiClient $culqiClient,
        protected OrderPushService $orderPushService,
    ) {}

    /**
     * @param  array{
     *     customer_name: string,
     *     customer_email: string,
     *     customer_phone: string,
     *     customer_document_type: string,
     *     customer_document_number: string,
     *     shipping_country_id: int,
     *     shipping_department_id: int,
     *     shipping_province_id: int,
     *     shipping_district_id: int,
     *     shipping_address_line: string,
     *     shipping_reference?: string|null,
     *     shipping_option: string
     * }  $checkoutData
     * @return array{ok: bool, message: string, code: string, order_token?: string, amount_minor?: int, currency?: string}
     */
    public function prepareCheckoutSession(array $checkoutData, ?User $user = null): array
    {
        $items = $this->cartService->detailedItems();

        if ($items->isEmpty()) {
            return [
                'ok' => false,
                'code' => 'empty_cart',
                'message' => __('Your cart is empty.'),
            ];
        }

        foreach ($items as $item) {
            if ((int) $item['stock_available'] < (int) $item['quantity']) {
                return [
                    'ok' => false,
                    'code' => 'stock_limit',
                    'message' => __('Some items exceed the available stock.'),
                ];
            }
        }

        $subtotal = $items->sum(fn (array $item): int => (int) $item['line_total']);
        $shippingTotal = $this->resolveShippingTotal(
            (int) $checkoutData['shipping_district_id'],
            $subtotal,
            (string) $checkoutData['shipping_option'],
        );
        $grandTotal = $subtotal + $shippingTotal;
        $currency = (string) config('services.main_store.currency', 'PEN');

        $orderToken = (string) Str::uuid();

        $itemsSnapshot = $items->map(function (array $item): array {
            /** @var ProductVariant $variant */
            $variant = $item['variant'];

            return [
                'variant_id' => (int) $variant->id,
                'variant_external_id' => $variant->external_id,
                'sku' => $variant->sku ?? $variant->code,
                'name_snapshot' => $this->buildNameSnapshot($variant),
                'quantity' => (int) $item['quantity'],
                'unit_price' => (int) $item['unit_price'],
                'line_total' => (int) $item['line_total'],
            ];
        })->all();

        $this->writeCheckoutSession($orderToken, [
            'checkout' => $checkoutData,
            'items' => $itemsSnapshot,
            'totals' => [
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'tax_total' => 0,
                'grand_total' => $grandTotal,
                'currency' => $currency,
            ],
            'user_id' => $user?->id,
            'created_at' => now()->toDateTimeString(),
        ]);

        return [
            'ok' => true,
            'code' => 'pending_order_created',
            'message' => __('Continue with payment.'),
            'order_token' => $orderToken,
            'amount_minor' => $grandTotal,
            'currency' => $currency,
        ];
    }

    /**
     * @return array{ok: bool, code: string, message: string, thank_you_url?: string}
     */
    public function confirmCharge(string $orderToken, string $culqiTokenId): array
    {
        $existingOrder = Order::query()
            ->where('order_token', $orderToken)
            ->where('source', 'local_checkout')
            ->first();

        if ($existingOrder && $existingOrder->payment_status === 'paid') {
            return [
                'ok' => true,
                'code' => 'already_paid',
                'message' => __('Payment already confirmed.'),
                'thank_you_url' => route('storefront.checkout.thank-you', ['orderToken' => $existingOrder->order_token]),
            ];
        }

        $sessionData = $this->getCheckoutSession($orderToken);

        if (! $sessionData) {
            return [
                'ok' => false,
                'code' => 'checkout_expired',
                'message' => __('Checkout session expired. Please start again.'),
            ];
        }

        $itemsSnapshot = $sessionData['items'] ?? [];

        if (! is_array($itemsSnapshot) || $itemsSnapshot === []) {
            return [
                'ok' => false,
                'code' => 'empty_cart',
                'message' => __('Your cart is empty.'),
            ];
        }

        if (! $this->hasStockAvailable($itemsSnapshot)) {
            return [
                'ok' => false,
                'code' => 'stock_limit',
                'message' => __('Some items exceed the available stock.'),
            ];
        }

        $checkoutData = (array) ($sessionData['checkout'] ?? []);
        $totals = (array) ($sessionData['totals'] ?? []);
        $currency = (string) ($totals['currency'] ?? config('services.main_store.currency', 'PEN'));
        $grandTotal = (int) ($totals['grand_total'] ?? 0);

        try {
            $response = $this->culqiClient->createCharge([
                'amount' => $grandTotal,
                'currency_code' => $currency,
                'email' => (string) ($checkoutData['customer_email'] ?? ''),
                'source_id' => $culqiTokenId,
                'token_id' => $culqiTokenId,
                'description' => sprintf('Checkout order %s', $orderToken),
                'metadata' => [
                    'order_token' => $orderToken,
                    'document_type' => (string) ($checkoutData['customer_document_type'] ?? ''),
                    'document_number' => (string) ($checkoutData['customer_document_number'] ?? ''),
                ],
            ]);

            $normalized = $this->culqiClient->normalizeChargeResponse($response);

            $order = DB::transaction(function () use ($checkoutData, $sessionData, $normalized, $culqiTokenId, $orderToken, $currency): Order {
                $totals = (array) ($sessionData['totals'] ?? []);

                $order = Order::query()->create([
                    'order_token' => $orderToken,
                    'source' => 'local_checkout',
                    'user_id' => $sessionData['user_id'] ?? null,
                    'main_store_external_order_id' => $orderToken,
                    'status' => 'paid',
                    'payment_gateway' => 'culqi',
                    'payment_status' => 'paid',
                    'payment_reference' => $normalized['id'] !== '' ? $normalized['id'] : $culqiTokenId,
                    'payment_error_code' => null,
                    'payment_error_message' => null,
                    'paid_at' => now(),
                    'currency' => $currency,
                    'customer_name' => $checkoutData['customer_name'] ?? null,
                    'customer_email' => $checkoutData['customer_email'] ?? null,
                    'customer_phone' => $checkoutData['customer_phone'] ?? null,
                    'customer_document_type' => $checkoutData['customer_document_type'] ?? null,
                    'customer_document_number' => $checkoutData['customer_document_number'] ?? null,
                    'shipping_country_id' => $checkoutData['shipping_country_id'] ?? null,
                    'shipping_department_id' => $checkoutData['shipping_department_id'] ?? null,
                    'shipping_province_id' => $checkoutData['shipping_province_id'] ?? null,
                    'shipping_district_id' => $checkoutData['shipping_district_id'] ?? null,
                    'shipping_address_line' => $checkoutData['shipping_address_line'] ?? null,
                    'shipping_reference' => $checkoutData['shipping_reference'] ?? null,
                    'shipping_method' => $checkoutData['shipping_option'] ?? null,
                    'subtotal' => (int) ($totals['subtotal'] ?? 0),
                    'discount_total' => 0,
                    'shipping_total' => (int) ($totals['shipping_total'] ?? 0),
                    'tax_total' => (int) ($totals['tax_total'] ?? 0),
                    'grand_total' => (int) ($totals['grand_total'] ?? 0),
                    'placed_at' => now(),
                    'push_status' => 'pending',
                    'push_attempts' => 0,
                ]);

                $itemsSnapshot = is_array($sessionData['items'] ?? null) ? $sessionData['items'] : [];

                $orderRows = collect($itemsSnapshot)
                    ->map(fn (array $item): array => [
                        'order_id' => $order->id,
                        'variant_external_id' => $item['variant_external_id'] ?? null,
                        'sku' => $item['sku'] ?? null,
                        'name_snapshot' => (string) ($item['name_snapshot'] ?? __('Product')),
                        'qty' => (int) ($item['quantity'] ?? 1),
                        'unit_price' => (int) ($item['unit_price'] ?? 0),
                        'line_total' => (int) ($item['line_total'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->all();

                if ($orderRows !== []) {
                    OrderItem::query()->insert($orderRows);
                }

                return $order;
            });

            $this->cartService->clear();
            $this->clearCheckoutSession($orderToken);

            $pushResult = $this->orderPushService->push($order);
            $this->orderPushService->applyPushResult($order, $pushResult);

            if (! $pushResult['ok']) {
                PushOrderToMainStoreJob::dispatch($order->id);
            }

            return [
                'ok' => true,
                'code' => 'payment_successful',
                'message' => __('Payment approved.'),
                'thank_you_url' => route('storefront.checkout.thank-you', ['orderToken' => $orderToken]),
            ];
        } catch (RequestException $exception) {
            $errorPayload = $exception->response?->json() ?? [];
            $rawErrorBody = (string) $exception->response?->body();
            $errorCode = (string) Arr::get($errorPayload, 'merchant_message', Arr::get($errorPayload, 'code', 'payment_failed'));
            $errorMessage = (string) Arr::get($errorPayload, 'user_message', __('The payment was declined. Please verify your card information.'));
            $normalizedErrorContext = mb_strtolower($errorCode.' '.$errorMessage.' '.$rawErrorBody);

            if (str_contains($normalizedErrorContext, '3ds') || str_contains($normalizedErrorContext, 'iins')) {
                $errorCode = 'unsupported_3ds_iins';
                $errorMessage = __('This card is not supported in this checkout. Please try another card or pay with Yape.');
            }

            return [
                'ok' => false,
                'code' => 'payment_failed',
                'message' => $errorMessage,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'code' => 'gateway_not_configured',
                'message' => __('Payment gateway is not configured. Configure Culqi keys and retry.'),
            ];
        }
    }

    public function resolveShippingTotal(int $districtId, int $subtotalMinor, string $shippingOption): int
    {
        $baseShipping = $this->resolveBaseShippingTotal($districtId);

        if ($shippingOption === 'express') {
            return $baseShipping * 2;
        }

        if ($subtotalMinor >= 10000) {
            return 0;
        }

        return $baseShipping;
    }

    public function resolveBaseShippingTotal(int $districtId): int
    {
        return (int) District::query()
            ->whereKey($districtId)
            ->value('shipping_price');
    }

    public function isExpressAvailable(?int $districtId, ?int $provinceId): bool
    {
        if ($districtId !== null && $districtId > 0) {
            return District::query()
                ->whereKey($districtId)
                ->where('has_delivery_express', true)
                ->exists();
        }

        if ($provinceId !== null && $provinceId > 0) {
            return Province::query()
                ->whereKey($provinceId)
                ->whereHas('districts', fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('has_delivery_express', true))
                ->exists();
        }

        return false;
    }

    public function markOrderFailed(string $orderToken, string $message, string $errorCode): void
    {
        $order = Order::query()
            ->where('order_token', $orderToken)
            ->where('source', 'local_checkout')
            ->first();

        if ($order) {
            $order->update([
                'status' => 'failed',
                'payment_status' => 'failed',
                'payment_error_code' => $errorCode,
                'payment_error_message' => $message,
            ]);

            return;
        }

        $this->clearCheckoutSession($orderToken);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function hasStockAvailable(array $items): bool
    {
        $variantIds = collect($items)
            ->pluck('variant_id')
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($variantIds->isEmpty()) {
            return false;
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->get(['id', 'stock_available'])
            ->keyBy('id');

        foreach ($items as $item) {
            $variantId = (int) ($item['variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($variantId <= 0 || $quantity <= 0) {
                return false;
            }

            $variant = $variants->get($variantId);

            if (! $variant) {
                return false;
            }

            if ((int) $variant->stock_available < $quantity) {
                return false;
            }
        }

        return true;
    }

    protected function buildNameSnapshot(ProductVariant $variant): string
    {
        $productName = (string) ($variant->product?->name ?? __('Product'));
        $variantSuffix = trim(collect([
            $variant->size ? __('Size :size', ['size' => $variant->size]) : null,
            $variant->color ? __('Color :color', ['color' => $variant->color]) : null,
        ])->filter()->implode(' | '));

        return $variantSuffix !== '' ? "{$productName} ({$variantSuffix})" : $productName;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function writeCheckoutSession(string $orderToken, array $payload): void
    {
        $sessions = Session::get(self::SESSION_KEY, []);

        if (! is_array($sessions)) {
            $sessions = [];
        }

        $sessions[$orderToken] = $payload;

        Session::put(self::SESSION_KEY, $sessions);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getCheckoutSession(string $orderToken): ?array
    {
        $sessions = Session::get(self::SESSION_KEY, []);

        if (! is_array($sessions)) {
            return null;
        }

        $payload = $sessions[$orderToken] ?? null;

        if (! is_array($payload)) {
            return null;
        }

        return $payload;
    }

    protected function clearCheckoutSession(string $orderToken): void
    {
        $sessions = Session::get(self::SESSION_KEY, []);

        if (! is_array($sessions)) {
            Session::forget(self::SESSION_KEY);

            return;
        }

        unset($sessions[$orderToken]);

        if ($sessions === []) {
            Session::forget(self::SESSION_KEY);

            return;
        }

        Session::put(self::SESSION_KEY, $sessions);
    }
}
