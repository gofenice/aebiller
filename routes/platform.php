<?php

use App\Http\Controllers\Platform\Auth\LoginController;
use App\Http\Controllers\Platform\BillingController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SettingController;
use App\Http\Controllers\Platform\StoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform routes
|--------------------------------------------------------------------------
|
| Served on the central domain (pgbiller.com): where stores are created and
| their monthly payments are tracked. A store's own screens live in
| routes/web.php and answer on that store's subdomain.
|
*/

Route::middleware('guest:platform')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('platform.login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('platform')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('platform.logout');

    Route::get('/', DashboardController::class)->name('platform.home');

    Route::get('stores', [StoreController::class, 'index'])->name('platform.stores.index');
    Route::get('stores/create', [StoreController::class, 'create'])->name('platform.stores.create');
    Route::post('stores', [StoreController::class, 'store'])->name('platform.stores.store');
    Route::get('stores/{store}', [StoreController::class, 'show'])->name('platform.stores.show');
    Route::get('stores/{store}/edit', [StoreController::class, 'edit'])->name('platform.stores.edit');
    Route::put('stores/{store}', [StoreController::class, 'update'])->name('platform.stores.update');
    Route::post('stores/{store}/suspend', [StoreController::class, 'suspend'])->name('platform.stores.suspend');
    Route::post('stores/{store}/reactivate', [StoreController::class, 'reactivate'])->name('platform.stores.reactivate');

    // Monthly subscriptions. "generate" is declared first so it is not read
    // as an invoice number.
    Route::get('billing', [BillingController::class, 'index'])->name('platform.billing.index');
    Route::post('billing/generate', [BillingController::class, 'generate'])->name('platform.billing.generate');
    Route::get('billing/{invoice}', [BillingController::class, 'show'])->name('platform.billing.show');
    Route::post('billing/{invoice}/payments', [BillingController::class, 'pay'])->name('platform.billing.pay');
    Route::post('billing/{invoice}/void', [BillingController::class, 'void'])->name('platform.billing.void');
    Route::post('billing/{invoice}/email', [BillingController::class, 'notify'])->name('platform.billing.notify');

    Route::resource('plans', PlanController::class)->except('show')->names('platform.plans');

    // How the platform charges its shops.
    Route::get('settings', [SettingController::class, 'edit'])->name('platform.settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('platform.settings.update');
    Route::post('settings/razorpay/test', [SettingController::class, 'test'])->name('platform.settings.razorpay.test');
});
