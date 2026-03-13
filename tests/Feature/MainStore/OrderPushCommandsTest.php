<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.main_store.base_url', 'https://main-store.test');
    config()->set('services.main_store.token', 'test-token');
});

it('creates an external order through artisan command and stores local tracking', function () {
    Http::fake([
        'https://main-store.test/api/v1/orders' => Http::response([
            'data' => [
                'id' => 9811,
                'status' => 'pending',
                'currency' => 'USD',
                'grand_total' => 20000,
            ],
        ], 201),
    ]);

    $payload = [
        'external_order_id' => 'remote-1001',
        'currency' => 'USD',
        'discount_amount' => 0,
        'shipping_cost' => 0,
        'order_status' => 'pending',
        'payment_status' => 'unpaid',
        'customer' => [
            'email' => 'buyer@example.com',
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
        ],
        'items' => [
            ['sku' => 'NIKE-PEG-42', 'quantity' => 2, 'price_per_unit' => 10000],
        ],
    ];

    $this->artisan('main-store:orders:create', [
        '--payload' => json_encode($payload, JSON_THROW_ON_ERROR),
    ])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://main-store.test/api/v1/orders'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization')
            && $request['external_order_id'] === 'remote-1001';
    });

    $order = Order::query()->where('main_store_external_order_id', 'remote-1001')->firstOrFail();

    expect($order->external_id)->toBe(9811);
    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->sku)->toBe('NIKE-PEG-42');
});

it('is idempotent locally when same external_order_id is sent twice', function () {
    Http::fake([
        'https://main-store.test/api/v1/orders' => Http::response([
            'data' => [
                'id' => 9811,
                'status' => 'pending',
                'currency' => 'USD',
                'grand_total' => 20000,
            ],
        ], 201),
    ]);

    $payload = [
        'external_order_id' => 'remote-1001',
        'currency' => 'USD',
        'discount_amount' => 0,
        'shipping_cost' => 0,
        'order_status' => 'pending',
        'payment_status' => 'unpaid',
        'customer' => [
            'email' => 'buyer@example.com',
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
        ],
        'items' => [
            ['sku' => 'NIKE-PEG-42', 'quantity' => 2, 'price_per_unit' => 10000],
        ],
    ];

    $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->artisan('main-store:orders:create', ['--payload' => $encodedPayload])->assertSuccessful();
    $this->artisan('main-store:orders:create', ['--payload' => $encodedPayload])->assertSuccessful();

    expect(Order::query()->where('main_store_external_order_id', 'remote-1001')->count())->toBe(1);
});

it('cancels an external order and updates local status immediately', function () {
    $order = Order::factory()->create([
        'main_store_external_order_id' => 'remote-1001',
        'status' => 'paid',
        'is_refunded' => false,
    ]);

    Http::fake([
        'https://main-store.test/api/v1/orders/remote-1001/cancel' => Http::response([
            'data' => [
                'external_order_id' => 'remote-1001',
                'status' => 'cancelled',
            ],
        ]),
    ]);

    $this->artisan('main-store:orders:cancel', [
        'external_order_id' => 'remote-1001',
        '--note' => 'Customer requested cancellation',
        '--mark-refunded' => true,
    ])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://main-store.test/api/v1/orders/remote-1001/cancel'
            && $request->method() === 'POST'
            && $request['note'] === 'Customer requested cancellation'
            && $request['mark_refunded'] === true;
    });

    $order->refresh();

    expect($order->status)->toBe('cancelled');
    expect($order->cancellation_note)->toBe('Customer requested cancellation');
    expect($order->is_refunded)->toBeTrue();
});
