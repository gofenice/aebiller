<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Services\ProfitLossReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Everything at or below its reorder level, ready to turn into a purchase order.
     */
    public function lowStock(Request $request): View
    {
        $products = Product::query()
            ->with(['category', 'unit', 'supplier'])
            ->active()
            ->when($request->string('include')->toString() !== 'out', fn ($query) => $query->where(function ($query): void {
                $query->lowStock()->orWhere('current_stock', '<=', 0);
            }))
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            ->orderByRaw('(current_stock <= 0) desc')
            ->orderBy('current_stock')
            ->paginate(30)
            ->withQueryString();

        return view('reports.low-stock', [
            'products' => $products,
            'categories' => Category::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Batches that have gone off or are about to.
     */
    public function expiry(Request $request): View
    {
        $days = (int) ($request->integer('days') ?: config('inventory.expiry_alert_days'));

        $batches = StockBatch::query()
            ->with(['product.unit', 'product.category', 'supplier'])
            ->inStock()
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', now()->addDays($days)->toDateString())
            ->orderBy('expires_on')
            ->paginate(30)
            ->withQueryString();

        return view('reports.expiry', [
            'batches' => $batches,
            'days' => $days,
            'expiredCount' => StockBatch::inStock()->expired()->count(),
        ]);
    }

    /**
     * Stock on hand valued at cost and at retail, grouped by category.
     */
    public function valuation(Request $request): View
    {
        $products = Product::query()
            ->with(['category', 'unit'])
            ->where('current_stock', '>', 0)
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            ->orderByRaw('current_stock * cost_price desc')
            ->paginate(30)
            ->withQueryString();

        $byCategory = Product::query()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereNull('products.deleted_at')
            ->select(
                'categories.name',
                DB::raw('count(*) as products'),
                DB::raw('coalesce(sum(products.current_stock * products.cost_price), 0) as cost_value'),
                DB::raw('coalesce(sum(products.current_stock * products.selling_price), 0) as retail_value'),
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('cost_value')
            ->get();

        return view('reports.valuation', [
            'products' => $products,
            'byCategory' => $byCategory,
            'categories' => Category::active()->orderBy('name')->get(),
            'totals' => [
                'cost' => (float) $byCategory->sum('cost_value'),
                'retail' => (float) $byCategory->sum('retail_value'),
            ],
        ]);
    }

    /**
     * What customers still owe on credit bills, oldest debt first.
     */
    public function credit(Request $request): View
    {
        $this->authorize('run-till');

        $query = Sale::query()
            ->with(['customer', 'cashier'])
            ->outstanding()
            ->when($request->filled('customer'), fn ($query) => $query->where('customer_id', $request->integer('customer')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = $request->string('search')->toString();
                $query->where(function ($query) use ($term): void {
                    $query->where('invoice_no', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_phone', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('older_than'), fn ($query) => $query->whereDate('sold_at', '<=', now()->subDays($request->integer('older_than'))->toDateString()));

        $byCustomer = Sale::query()
            ->outstanding()
            ->select(
                'customer_id',
                DB::raw('count(*) as bills'),
                DB::raw('coalesce(sum(amount_outstanding), 0) as owed'),
                DB::raw('min(sold_at) as oldest_sold_at'),
            )
            ->groupBy('customer_id')
            ->orderByDesc('owed')
            ->with('customer')
            ->get();

        // Balances carried over from before the shop billed here count
        // towards what a customer owes, whether or not they have a bill.
        $carriedOver = Customer::where('opening_due_outstanding', '>', 0)->get();

        return view('reports.credit', [
            'sales' => $query->orderBy('sold_at')->paginate(25)->withQueryString(),
            'byCustomer' => $byCustomer,
            'carriedOver' => $carriedOver,
            'openingTotal' => (float) $carriedOver->sum('opening_due_outstanding'),
            'totalOwed' => (float) $byCustomer->sum('owed') + (float) $carriedOver->sum('opening_due_outstanding'),
            'settlementMethods' => PaymentMethod::settlementMethods(),
            'collectedToday' => (float) CreditPayment::whereDate('received_at', today())->sum('amount'),
        ]);
    }

    /**
     * Income against outgoings for a chosen period.
     */
    public function profitLoss(Request $request, ProfitLossReport $report): View
    {
        $this->authorize('manage-expenses');

        $preset = $request->string('period')->toString() ?: 'this-month';

        if ($request->filled('from') || $request->filled('to')) {
            $preset = 'custom';
        }

        [$from, $to] = $preset === 'custom'
            ? [
                CarbonImmutable::parse($request->date('from') ?? now()->startOfMonth())->startOfDay(),
                CarbonImmutable::parse($request->date('to') ?? now())->startOfDay(),
            ]
            : ProfitLossReport::range($preset);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return view('reports.profit-loss', [
            ...$report->build($from, $to),
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'presets' => ProfitLossReport::presets(),
            'days' => $from->diffInDays($to) + 1,
        ]);
    }
}
