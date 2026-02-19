<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::redirect('/', '/admin/dashboard');

        Route::livewire('dashboard', 'pages::admin.dashboard')->name('dashboard');
        Route::livewire('brands', 'pages::admin.brands')->name('brands');
        Route::livewire('categories', 'pages::admin.categories')->name('categories');
        Route::livewire('products', 'pages::admin.products')->name('products');
        Route::livewire('inventory', 'pages::admin.inventory')->name('inventory');
        Route::livewire('orders', 'pages::admin.orders')->name('orders');
    });

require __DIR__.'/settings.php';
