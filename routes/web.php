<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicBillController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockEntryController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Public: the address encoded in the receipt QR code.
Route::get('bill/{sale}', PublicBillController::class)->name('bill.show');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Till
    Route::get('billing', [BillingController::class, 'create'])->name('billing.create');
    Route::post('billing', [BillingController::class, 'store'])->name('billing.store');
    Route::get('billing/scan', [BillingController::class, 'scan'])->name('billing.scan');

    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');

    // Inventory master
    Route::get('products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');
    Route::patch('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');
    Route::resource('products', ProductController::class);

    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('brands', BrandController::class)->except('show', 'create', 'edit');
    Route::resource('units', UnitController::class)->except('show', 'create', 'edit');
    Route::resource('suppliers', SupplierController::class)->except('show');

    // Stock transactions
    Route::resource('stock-entries', StockEntryController::class)->except('edit', 'update');
    Route::resource('stock-adjustments', StockAdjustmentController::class)->except('edit', 'update');
    Route::get('stock-movements', StockMovementController::class)->name('stock-movements.index');

    // Money out
    Route::resource('expenses', ExpenseController::class);
    Route::resource('expense-categories', ExpenseCategoryController::class)
        ->only('index', 'store', 'update', 'destroy')
        ->parameters(['expense-categories' => 'expenseCategory']);

    // Reports
    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function (): void {
        Route::get('low-stock', 'lowStock')->name('low-stock');
        Route::get('expiry', 'expiry')->name('expiry');
        Route::get('valuation', 'valuation')->name('valuation');
        Route::get('profit-loss', 'profitLoss')->name('profit-loss');
    });

    // Super admin only
    Route::middleware('super_admin')->group(function (): void {
        Route::resource('users', UserController::class)->except('show');
    });
});
