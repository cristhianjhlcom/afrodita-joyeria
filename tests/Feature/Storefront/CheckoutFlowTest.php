<?php

use App\Jobs\PushOrderToMainStoreJob;
use App\Models\Country;
use App\Models\Department;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\User;
use App\Services\Storefront\CartService;
use App\Services\Storefront\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAddressHierarchy(): array
{
    $country = Country::factory()->create();
    $department = Department::factory()->create([
        'country_id' => $country->id,
    ]);
    $province = Province::factory()->create([
        'country_id' => $country->id,
        'department_id' => $department->id,
        'shipping_price' => 1500,
    ]);
    $district = District::factory()->create([
        'country_id' => $country->id,
        'department_id' => $department->id,
        'province_id' => $province->id,
        'shipping_price' => 1900,
    ]);

    return [$country, $department, $province, $district];
}

function seedCheckoutCart(): ProductVariant
{
    $product = Product::factory()->create([
        'name' => 'Checkout Product',
        'slug' => 'checkout-product',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'external_id' => 666101,
        'sku' => 'AFR-CHK-666',
        'price' => 25000,
        'stock_available' => 5,
        'is_active' => true,
    ]);

    session([CartService::SESSION_KEY => [$variant->id => 2]]);

    return $variant;
}

beforeEach(function () {
    config()->set('services.culqi.public_key', 'pk_test_123');
    config()->set('services.culqi.secret_key', 'sk_test_123');
    config()->set('services.culqi.base_url', 'https://api.culqi.test/v2');
    config()->set('services.main_store.base_url', 'https://api.main-store.test');
    config()->set('services.main_store.token', 'main_store_token');
});

it('renders checkout page', function () {
    $this->get(route('storefront.checkout.show'))
        ->assertSuccessful()
        ->assertSee('Checkout');
});

it('detects existing email and displays warning state', function () {
    seedCheckoutCart();
    createAddressHierarchy();

    $user = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    Livewire::test('pages::storefront.checkout')
        ->set('customerEmail', $user->email)
        ->assertSet('existingEmailDetected', true);
});

it('creates order after payment and pushes to main store', function () {
    seedCheckoutCart();
    [$country, $department, $province, $district] = createAddressHierarchy();

    Http::fake([
        'https://api.culqi.test/v2/charges' => Http::response([
            'id' => 'chr_test_101',
            'outcome' => [
                'type' => 'venta_exitosa',
                'code' => 'AUT0000',
                'user_message' => 'Approved',
            ],
        ], 201),
        'https://api.main-store.test/api/v1/orders' => Http::response([
            'data' => [
                'id' => 901,
                'status' => 'paid',
                'currency' => 'PEN',
            ],
        ], 201),
    ]);

    $component = Livewire::test('pages::storefront.checkout')
        ->set('firstName', 'Ana')
        ->set('lastName', 'Perez')
        ->set('customerEmail', 'ana@example.com')
        ->set('customerPhone', '999 111 222')
        ->set('documentType', 'DNI')
        ->set('documentNumber', '12345678')
        ->set('shippingCountryId', (string) $country->id)
        ->set('shippingDepartmentId', (string) $department->id)
        ->set('shippingProvinceId', (string) $province->id)
        ->set('shippingDistrictId', (string) $district->id)
        ->set('shippingAddressLine', 'Av. Primavera 123')
        ->set('shippingReference', 'Casa blanca')
        ->call('startPayment')
        ->assertDispatched('checkout-open-culqi');

    $sessions = session(CheckoutService::SESSION_KEY, []);
    $orderToken = array_key_first($sessions);

    expect($orderToken)->not->toBeNull();
    expect(Order::query()->count())->toBe(0);

    $component
        ->call('confirmPayment', (string) $orderToken, 'tok_test_001')
        ->assertRedirect(route('storefront.checkout.thank-you', ['orderToken' => $orderToken]));

    $order = Order::query()->where('order_token', $orderToken)->firstOrFail();

    $order->refresh();

    expect($order->payment_status)->toBe('paid');
    expect($order->status)->toBe('paid');
    expect($order->push_status)->toBe('pushed');
    expect($order->main_store_order_id)->toBe(901);
    expect($order->customer_document_type)->toBe('DNI');
    expect($order->customer_document_number)->toBe('12345678');
    expect($order->customer_phone)->toBe('999111222');
    expect($order->shipping_method)->toBe('scheduled');
    expect($order->shipping_total)->toBe(0);
    expect(session(CartService::SESSION_KEY, []))->toBe([]);

    Http::assertSentCount(2);
});

it('queues retry when main store push fails', function () {
    Bus::fake();
    seedCheckoutCart();
    [$country, $department, $province, $district] = createAddressHierarchy();

    Http::fake([
        'https://api.culqi.test/v2/charges' => Http::response([
            'id' => 'chr_test_333',
            'outcome' => [
                'type' => 'venta_exitosa',
                'code' => 'AUT0000',
                'user_message' => 'Approved',
            ],
        ], 201),
        'https://api.main-store.test/api/v1/orders' => Http::response([
            'error' => [
                'code' => 'order_failed',
                'message' => 'Main store unavailable',
            ],
        ], 500),
    ]);

    $component = Livewire::test('pages::storefront.checkout')
        ->set('firstName', 'Ana')
        ->set('lastName', 'Perez')
        ->set('customerEmail', 'ana@example.com')
        ->set('customerPhone', '999 111 222')
        ->set('documentType', 'DNI')
        ->set('documentNumber', '12345678')
        ->set('shippingCountryId', (string) $country->id)
        ->set('shippingDepartmentId', (string) $department->id)
        ->set('shippingProvinceId', (string) $province->id)
        ->set('shippingDistrictId', (string) $district->id)
        ->set('shippingAddressLine', 'Av. Primavera 123')
        ->call('startPayment');

    $sessions = session(CheckoutService::SESSION_KEY, []);
    $orderToken = array_key_first($sessions);

    $component->call('confirmPayment', (string) $orderToken, 'tok_test_333');

    $order = Order::query()->where('order_token', $orderToken)->firstOrFail();

    expect($order->push_status)->toBe('failed');

    Bus::assertDispatched(PushOrderToMainStoreJob::class, function (PushOrderToMainStoreJob $job) use ($order): bool {
        return $job->orderId === $order->id;
    });
});

it('keeps cart when culqi charge is declined', function () {
    seedCheckoutCart();
    [$country, $department, $province, $district] = createAddressHierarchy();

    Http::fake([
        'https://api.culqi.test/v2/charges' => Http::response([
            'merchant_message' => 'card_declined',
            'user_message' => 'Payment declined',
        ], 402),
    ]);

    $component = Livewire::test('pages::storefront.checkout')
        ->set('firstName', 'Ana')
        ->set('lastName', 'Perez')
        ->set('customerEmail', 'ana@example.com')
        ->set('customerPhone', '999111222')
        ->set('documentType', 'CE')
        ->set('documentNumber', '000123456')
        ->set('shippingCountryId', (string) $country->id)
        ->set('shippingDepartmentId', (string) $department->id)
        ->set('shippingProvinceId', (string) $province->id)
        ->set('shippingDistrictId', (string) $district->id)
        ->set('shippingAddressLine', 'Av. Primavera 123')
        ->call('startPayment');

    $sessions = session(CheckoutService::SESSION_KEY, []);
    $orderToken = array_key_first($sessions);

    expect($orderToken)->not->toBeNull();

    $component
        ->call('confirmPayment', (string) $orderToken, 'tok_test_999')
        ->assertSet('feedbackSuccess', false)
        ->assertSet('feedbackMessage', 'Payment declined');

    expect(Order::query()->count())->toBe(0);
    expect(session(CartService::SESSION_KEY, []))->not->toBe([]);
});

it('shows fallback message when culqi responds with 3ds or iins errors', function () {
    seedCheckoutCart();
    [$country, $department, $province, $district] = createAddressHierarchy();

    Http::fake([
        'https://api.culqi.test/v2/charges' => Http::response([
            'merchant_message' => 'IINS',
            'user_message' => 'dont use 3DS authentication',
        ], 400),
        'https://api.main-store.test/api/v1/orders' => Http::response([], 500),
    ]);

    $component = Livewire::test('pages::storefront.checkout')
        ->set('firstName', 'Ana')
        ->set('lastName', 'Perez')
        ->set('customerEmail', 'ana@example.com')
        ->set('customerPhone', '999111222')
        ->set('documentType', 'PASSPORT')
        ->set('documentNumber', 'AB123456')
        ->set('shippingCountryId', (string) $country->id)
        ->set('shippingDepartmentId', (string) $department->id)
        ->set('shippingProvinceId', (string) $province->id)
        ->set('shippingDistrictId', (string) $district->id)
        ->set('shippingAddressLine', 'Av. Primavera 123')
        ->call('startPayment');

    $sessions = session(CheckoutService::SESSION_KEY, []);
    $orderToken = array_key_first($sessions);

    expect($orderToken)->not->toBeNull();

    $component
        ->call('confirmPayment', (string) $orderToken, 'tok_test_3ds')
        ->assertSet('feedbackSuccess', false)
        ->assertSet('feedbackMessage', 'Esta tarjeta no es compatible con este checkout. Prueba otra tarjeta o paga con Yape.');
});

it('validates document number by selected type', function () {
    seedCheckoutCart();
    [$country, $department, $province, $district] = createAddressHierarchy();

    Livewire::test('pages::storefront.checkout')
        ->set('firstName', 'Ana')
        ->set('lastName', 'Perez')
        ->set('customerEmail', 'ana@example.com')
        ->set('customerPhone', '999 111 222')
        ->set('documentType', 'DNI')
        ->set('documentNumber', 'A1234567')
        ->set('shippingCountryId', (string) $country->id)
        ->set('shippingDepartmentId', (string) $department->id)
        ->set('shippingProvinceId', (string) $province->id)
        ->set('shippingDistrictId', (string) $district->id)
        ->set('shippingAddressLine', 'Av. Primavera 123')
        ->call('startPayment')
        ->assertHasErrors(['documentNumber']);
});

it('applies express shipping as double base amount when express is enabled', function () {
    seedCheckoutCart();
    [$country, $department, $province, $district] = createAddressHierarchy();

    $district->update([
        'has_delivery_express' => true,
    ]);

    Http::fake([
        'https://api.culqi.test/v2/charges' => Http::response([
            'id' => 'chr_test_202',
            'outcome' => [
                'type' => 'venta_exitosa',
                'code' => 'AUT0000',
                'user_message' => 'Approved',
            ],
        ], 201),
        'https://api.main-store.test/api/v1/orders' => Http::response([
            'data' => [
                'id' => 902,
            ],
        ], 201),
    ]);

    $component = Livewire::test('pages::storefront.checkout')
        ->set('firstName', 'Ana')
        ->set('lastName', 'Perez')
        ->set('customerEmail', 'ana@example.com')
        ->set('customerPhone', '999 111 222')
        ->set('documentType', 'DNI')
        ->set('documentNumber', '12345678')
        ->set('shippingCountryId', (string) $country->id)
        ->set('shippingDepartmentId', (string) $department->id)
        ->set('shippingProvinceId', (string) $province->id)
        ->set('shippingDistrictId', (string) $district->id)
        ->set('shippingOption', 'express')
        ->set('shippingAddressLine', 'Av. Primavera 123')
        ->call('startPayment');

    $sessions = session(CheckoutService::SESSION_KEY, []);
    $orderToken = array_key_first($sessions);

    expect($orderToken)->not->toBeNull();

    $component->call('confirmPayment', (string) $orderToken, 'tok_test_202');

    $order = Order::query()->where('order_token', $orderToken)->firstOrFail();

    $order->refresh();

    expect($order->shipping_method)->toBe('express');
    expect($order->shipping_total)->toBe(3800);
});
