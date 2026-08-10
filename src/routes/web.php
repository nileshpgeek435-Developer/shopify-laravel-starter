<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ShopifyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/', DashboardController::class)->name('home');

    Route::prefix('api')->group(function () {
        Route::get('/shop', [ShopifyController::class, 'shop'])->name('api.shop');
        Route::get('/products', [ShopifyController::class, 'products'])->name('api.products');
    });
});
