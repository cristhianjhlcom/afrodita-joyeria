<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::redirect('/', '/admin/dashboard');

        Route::livewire('dashboard', 'pages::admin.dashboard')->name('dashboard');
        Route::livewire('brands', 'pages::admin.brands')->name('brands');
        Route::livewire('categories', 'pages::admin.categories')->name('categories');
        Route::livewire('products', 'pages::admin.products')->name('products');
        Route::livewire('products/{product}', 'pages::admin.product-detail')->name('products.show');
        Route::livewire('inventory', 'pages::admin.inventory')->name('inventory');
        Route::livewire('orders', 'pages::admin.orders')->name('orders');
        Route::livewire('sync-runs/{syncRun}', 'pages::admin.sync-run-detail')->name('sync-runs.show');
    });

require __DIR__.'/settings.php';
