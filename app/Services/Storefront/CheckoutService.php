<?php

namespace App\Services\Storefront;

use App\Models\District;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Province;
use App\Models\User;
use App\Services\Payments\CulqiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected CulqiClient $culqiClient,
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
    public function preparePendingOrder(array $checkoutData, ?User $user = null): array
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

        $order = DB::transaction(function () use ($checkoutData, $user, $subtotal, $shippingTotal, $grandTotal, $currency, $items): Order {
            $order = Order::query()->create([
                'order_token' => (string) Str::uuid(),
                'source' => 'local_checkout',
                'user_id' => $user?->id,
                'status' => 'pending',
                'payment_gateway' => 'culqi',
                'payment_status' => 'pending',
                'currency' => $currency,
                'customer_name' => $checkoutData['customer_name'],
                'customer_email' => $checkoutData['customer_email'],
                'customer_phone' => $checkoutData['customer_phone'],
                'customer_document_type' => $checkoutData['customer_document_type'],
                'customer_document_number' => $checkoutData['customer_document_number'],
                'shipping_country_id' => $checkoutData['shipping_country_id'],
                'shipping_department_id' => $checkoutData['shipping_department_id'],
                'shipping_province_id' => $checkoutData['shipping_province_id'],
                'shipping_district_id' => $checkoutData['shipping_district_id'],
                'shipping_address_line' => $checkoutData['shipping_address_line'],
                'shipping_reference' => $checkoutData['shipping_reference'] ?? null,
                'shipping_method' => $checkoutData['shipping_option'],
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'shipping_total' => $shippingTotal,
                'tax_total' => 0,
                'grand_total' => $grandTotal,
                'placed_at' => now(),
            ]);

            $orderRows = $items->map(function (array $item) use ($order): array {
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

        return [
            'ok' => true,
            'code' => 'pending_order_created',
            'message' => __('Continue with payment.'),
            'order_token' => (string) $order->order_token,
            'amount_minor' => $grandTotal,
            'currency' => $currency,
        ];
    }

    /**
     * @return array{ok: bool, code: string, message: string, thank_you_url?: string}
     */
    public function confirmCharge(string $orderToken, string $culqiTokenId): array
    {
        $order = Order::query()
            ->where('order_token', $orderToken)
            ->where('source', 'local_checkout')
            ->first();

        if (! $order) {
            return [
                'ok' => false,
                'code' => 'order_not_found',
                'message' => __('Checkout order was not found.'),
            ];
        }

        if ($order->payment_status === 'paid') {
            return [
                'ok' => true,
                'code' => 'already_paid',
                'message' => __('Payment already confirmed.'),
                'thank_you_url' => route('storefront.checkout.thank-you', ['orderToken' => $order->order_token]),
            ];
        }

        try {
            $response = $this->culqiClient->createCharge([
                'amount' => (int) $order->grand_total,
                'currency_code' => (string) $order->currency,
                'email' => (string) $order->customer_email,
                'source_id' => $culqiTokenId,
                'token_id' => $culqiTokenId,
                'description' => sprintf('Checkout order #%d', (int) $order->id),
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_token' => (string) $order->order_token,
                    'document_type' => (string) $order->customer_document_type,
                    'document_number' => (string) $order->customer_document_number,
                ],
            ]);

            $normalized = $this->culqiClient->normalizeChargeResponse($response);

            $order->update([
                'status' => 'paid',
                'payment_status' => 'paid',
                'payment_reference' => $normalized['id'] !== '' ? $normalized['id'] : $culqiTokenId,
                'payment_error_code' => null,
                'payment_error_message' => null,
                'paid_at' => now(),
            ]);

            $this->cartService->clear();

            return [
                'ok' => true,
                'code' => 'payment_successful',
                'message' => __('Payment approved.'),
                'thank_you_url' => route('storefront.checkout.thank-you', ['orderToken' => $order->order_token]),
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

            $order->update([
                'status' => 'failed',
                'payment_status' => 'failed',
                'payment_error_code' => $errorCode,
                'payment_error_message' => $errorMessage,
            ]);

            return [
                'ok' => false,
                'code' => 'payment_failed',
                'message' => $errorMessage,
            ];
        } catch (\Throwable $exception) {
            $order->update([
                'status' => 'failed',
                'payment_status' => 'failed',
                'payment_error_code' => 'gateway_not_configured',
                'payment_error_message' => __('Payment gateway is not configured.'),
            ]);

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
        Order::query()
            ->where('order_token', $orderToken)
            ->where('source', 'local_checkout')
            ->where('payment_status', 'pending')
            ->update([
                'status' => 'failed',
                'payment_status' => 'failed',
                'payment_error_code' => $errorCode,
                'payment_error_message' => $message,
            ]);
    }
}
