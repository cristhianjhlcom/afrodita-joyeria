<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createPaidCheckoutOrder(array $overrides = []): Order
{
    $order = Order::factory()->create(array_merge([
        'order_token' => (string) \Illuminate\Support\Str::uuid(),
        'source' => 'local_checkout',
        'status' => 'paid',
        'payment_status' => 'paid',
        'currency' => 'PEN',
        'customer_name' => 'Guest Buyer',
        'customer_email' => 'guest@example.com',
        'customer_phone' => '999000111',
        'subtotal' => 30000,
        'shipping_total' => 1500,
        'grand_total' => 31500,
    ], $overrides));

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'name_snapshot' => 'Ring Gold',
        'qty' => 1,
        'unit_price' => 30000,
        'line_total' => 30000,
    ]);

    return $order;
}

it('renders thank you page', function () {
    $order = createPaidCheckoutOrder();

    $this->get(route('storefront.checkout.thank-you', ['orderToken' => $order->order_token]))
        ->assertSuccessful()
        ->assertSee('Thank you for your purchase!');
});

it('allows guest to create account from thank-you page', function () {
    $order = createPaidCheckoutOrder();

    Livewire::test('pages::storefront.checkout-thank-you', ['orderToken' => $order->order_token])
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('createAccount')
        ->assertSet('feedbackSuccess', true)
        ->assertSet('feedbackMessage', 'Your account has been created successfully.');

    $user = User::query()->where('email', 'guest@example.com')->firstOrFail();

    $order->refresh();

    $this->assertAuthenticatedAs($user);
    expect($order->user_id)->toBe($user->id);
});

it('does not show account creation form when email already exists', function () {
    User::factory()->create([
        'email' => 'guest@example.com',
    ]);

    $order = createPaidCheckoutOrder();

    Livewire::test('pages::storefront.checkout-thank-you', ['orderToken' => $order->order_token])
        ->assertSet('canCreateAccount', false)
        ->assertSee('Already have an account?');
});

it('shows my orders button for authenticated users on thank-you page', function () {
    $user = User::factory()->create([
        'email' => 'guest@example.com',
    ]);

    $order = createPaidCheckoutOrder([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('storefront.checkout.thank-you', ['orderToken' => $order->order_token]))
        ->assertSuccessful()
        ->assertSee(route('settings.orders'))
        ->assertSee('Go to my orders');
});
