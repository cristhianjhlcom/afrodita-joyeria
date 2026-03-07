<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests from my orders page to login', function () {
    $this->get(route('settings.orders'))
        ->assertRedirect(route('login'));
});

it('shows authenticated user local checkout orders', function () {
    $user = User::factory()->create([
        'email' => 'buyer@example.com',
    ]);

    $ownedOrder = Order::factory()->create([
        'source' => 'local_checkout',
        'user_id' => $user->id,
        'customer_email' => 'buyer@example.com',
        'payment_status' => 'paid',
        'status' => 'paid',
        'grand_total' => 54000,
    ]);

    OrderItem::factory()->create([
        'order_id' => $ownedOrder->id,
        'name_snapshot' => 'Anillo Dorado',
        'qty' => 2,
        'line_total' => 54000,
    ]);

    $emailMatchedOrder = Order::factory()->create([
        'source' => 'local_checkout',
        'user_id' => null,
        'customer_email' => 'buyer@example.com',
        'payment_status' => 'paid',
        'status' => 'paid',
        'grand_total' => 28000,
    ]);

    OrderItem::factory()->create([
        'order_id' => $emailMatchedOrder->id,
        'name_snapshot' => 'Pulsera Plata',
        'qty' => 1,
        'line_total' => 28000,
    ]);

    $hiddenOrder = Order::factory()->create([
        'source' => 'local_checkout',
        'user_id' => User::factory(),
        'customer_email' => 'other@example.com',
        'grand_total' => 99000,
    ]);

    OrderItem::factory()->create([
        'order_id' => $hiddenOrder->id,
        'name_snapshot' => 'Producto Oculto',
        'qty' => 1,
        'line_total' => 99000,
    ]);

    $this->actingAs($user)
        ->get(route('settings.orders'))
        ->assertSuccessful()
        ->assertSee('My Orders')
        ->assertSee('Anillo Dorado')
        ->assertSee('Pulsera Plata')
        ->assertDontSee('Producto Oculto');
});
