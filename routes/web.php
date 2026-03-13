<?php

use App\Http\Controllers\PolicyController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\Department;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::storefront.catalog')->name('home');
Route::livewire('/producto/{product:slug}', 'pages::storefront.product-detail')->name('storefront.products.show');
Route::livewire('/carrito', 'pages::storefront.cart')->name('storefront.cart.show');
Route::livewire('/checkout', 'pages::storefront.checkout')->name('storefront.checkout.show');
Route::livewire('/checkout/thank-you/{orderToken}', 'pages::storefront.checkout-thank-you')->name('storefront.checkout.thank-you');

Route::get('/politicas/terminos-y-condiciones', [PolicyController::class, 'show'])
    ->defaults('policy', 'terminos-y-condiciones')
    ->name('policies.terms');
Route::get('/politicas/privacidad', [PolicyController::class, 'show'])
    ->defaults('policy', 'privacidad')
    ->name('policies.privacy');
Route::get('/politicas/devoluciones', [PolicyController::class, 'show'])
    ->defaults('policy', 'devoluciones')
    ->name('policies.returns');
Route::get('/politicas/envios', [PolicyController::class, 'show'])
    ->defaults('policy', 'envios')
    ->name('policies.shipping');

Route::middleware(['auth', 'verified', 'admin', 'admin-locale'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::redirect('/', '/admin/dashboard');

        Route::livewire('dashboard', 'pages::admin.dashboard')
            ->can('viewAny', SyncRun::class)
            ->name('dashboard');
        Route::livewire('brands', 'pages::admin.brands')
            ->can('viewAny', Brand::class)
            ->name('brands');
        Route::livewire('categories', 'pages::admin.categories')
            ->can('viewAny', Category::class)
            ->name('categories');
        Route::livewire('products', 'pages::admin.products')
            ->can('viewAny', Product::class)
            ->name('products');
        Route::livewire('products/{product}', 'pages::admin.product-detail')
            ->can('view', 'product')
            ->name('products.show');
        Route::livewire('inventory', 'pages::admin.inventory')
            ->can('viewAny', ProductVariant::class)
            ->name('inventory');
        Route::livewire('orders', 'pages::admin.orders')
            ->can('viewAny', Order::class)
            ->name('orders');
        Route::livewire('countries', 'pages::admin.countries')
            ->can('viewAny', Country::class)
            ->name('countries');
        Route::livewire('departments', 'pages::admin.departments')
            ->can('viewAny', Department::class)
            ->name('departments');
        Route::livewire('provinces', 'pages::admin.provinces')
            ->can('viewAny', Province::class)
            ->name('provinces');
        Route::livewire('districts', 'pages::admin.districts')
            ->can('viewAny', District::class)
            ->name('districts');
        Route::livewire('addresses', 'pages::admin.addresses')
            ->can('viewAny', District::class)
            ->name('addresses');
        Route::livewire('sync-runs/{syncRun}', 'pages::admin.sync-run-detail')
            ->can('view', 'syncRun')
            ->name('sync-runs.show');
    });

require __DIR__.'/settings.php';
