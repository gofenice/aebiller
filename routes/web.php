<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditPaymentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LoyaltyCardController;
use App\Http\Controllers\LoyaltyReportController;
use App\Http\Controllers\LoyaltySettingController;
use App\Http\Controllers\OpeningDueController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicBillController;
use App\Http\Controllers\PublicMemberController;
use App\Http\Controllers\RedemptionOtpController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockEntryController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TillMemberController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsAppBillController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Public: the address encoded in the receipt QR code.
Route::get('bill/{sale}', PublicBillController::class)->name('bill.show');

// Public: the address in the QR code on the back of a loyalty card.
Route::get('member/{customer:uuid}', PublicMemberController::class)->name('member.show');

// The guard is named rather than left to the default: this application also
// has a platform guard, and a store must only ever mean its own staff.
Route::middleware('guest:web')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

// The one-time link handed out at the end of sign-up, so a new owner lands
// inside their shop rather than at a password box. Signed and short-lived.
Route::get('welcome/{user}', [LoginController::class, 'welcome'])
    ->middleware('signed')
    ->name('store.welcome');

Route::middleware('auth:web')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // What this shop owes the platform. Reachable even when the shop is locked,
    // because paying is the way out of that.
    Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('subscription/{invoice}/pay', [SubscriptionController::class, 'pay'])->name('subscription.pay');
    Route::get('subscription/callback', [SubscriptionController::class, 'callback'])->name('subscription.callback');
    Route::post('subscription/auto-charge', [SubscriptionController::class, 'enableAutoCharge'])->name('subscription.auto-charge.enable');
    Route::delete('subscription/auto-charge', [SubscriptionController::class, 'disableAutoCharge'])->name('subscription.auto-charge.disable');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Till
    Route::get('billing', [BillingController::class, 'create'])->name('billing.create');
    Route::post('billing', [BillingController::class, 'store'])->name('billing.store');
    Route::get('billing/scan', [BillingController::class, 'scan'])->name('billing.scan');
    Route::get('billing/member', [TillMemberController::class, 'lookup'])->name('billing.member');
    Route::post('billing/member', [TillMemberController::class, 'store'])->name('billing.member.store');

    // The code a member confirms their own redemption with, so a found card
    // cannot be spent without their handset.
    Route::post('billing/redemption-code', [RedemptionOtpController::class, 'send'])->name('billing.otp.send');
    Route::post('billing/redemption-code/verify', [RedemptionOtpController::class, 'verify'])->name('billing.otp.verify');
    Route::post('billing/redemption-code/override', [RedemptionOtpController::class, 'override'])->name('billing.otp.override');

    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
    Route::post('sales/{sale}/whatsapp', WhatsAppBillController::class)->name('sales.whatsapp');
    Route::post('sales/{sale}/credit-payments', [CreditPaymentController::class, 'store'])->name('sales.credit-payments.store');

    // Loyalty members and their cards
    Route::get('customers/cards', [LoyaltyCardController::class, 'sheet'])->name('customers.cards');
    Route::get('customers/card-designs', [LoyaltyCardController::class, 'designs'])->name('customers.card-designs');
    Route::get('customers/{customer}/card', [LoyaltyCardController::class, 'show'])->name('customers.card');
    Route::post('customers/{customer}/card', [CustomerController::class, 'replaceCard'])->name('customers.replace-card');
    Route::post('customers/{customer}/points', [CustomerController::class, 'adjustPoints'])->name('customers.adjust-points');
    Route::post('customers/{customer}/opening-due', [OpeningDueController::class, 'store'])->name('customers.opening-due.store');
    Route::post('customers/{customer}/opening-due/payments', [OpeningDueController::class, 'pay'])->name('customers.opening-due.pay');
    Route::resource('customers', CustomerController::class);

    // Inventory master
    Route::get('products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');
    Route::get('products/labels', [ProductController::class, 'labels'])->name('products.labels');
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
        Route::get('credit', 'credit')->name('credit');
    });
    Route::get('reports/loyalty', LoyaltyReportController::class)->name('reports.loyalty');

    // Super admin only
    Route::middleware('super_admin')->group(function (): void {
        Route::resource('users', UserController::class)->except('show');

        Route::get('loyalty/settings', [LoyaltySettingController::class, 'edit'])->name('loyalty.settings');
        Route::put('loyalty/settings', [LoyaltySettingController::class, 'update'])->name('loyalty.settings.update');
    });
});
