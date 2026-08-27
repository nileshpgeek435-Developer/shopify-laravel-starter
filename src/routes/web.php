<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ShopifyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/', DashboardController::class)->name('home');

    // Application billing UI (package keeps /billing/{plan} + /billing/process/{plan})
    Route::get('/plans', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/plans/{plan}/subscribe', [BillingController::class, 'subscribe'])
        ->whereNumber('plan')
        ->name('billing.subscribe');
    Route::post('/plans/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::get('/api/billing', [BillingController::class, 'status'])->name('api.billing');

    Route::prefix('api')->group(function () {
        Route::get('/shop', [ShopifyController::class, 'shop'])->name('api.shop');
        Route::get('/products', [ShopifyController::class, 'products'])->name('api.products');
    });
});
