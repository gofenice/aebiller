<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockEntry;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Inventory overview: what is on the shelf, what needs ordering, what is going off.
     */
    public function __invoke(): View
    {
        $expiryDays = config('inventory.expiry_alert_days');

        return view('dashboard', [
            'stats' => [
                'products' => Product::count(),
                'packaged' => Product::where('type', ProductType::Packaged)->count(),
                'loose' => Product::where('type', ProductType::Loose)->count(),
                'stockValue' => (float) Product::query()
                    ->select(DB::raw('coalesce(sum(current_stock * cost_price), 0) as value'))
                    ->value('value'),
                'retailValue' => (float) Product::query()
                    ->select(DB::raw('coalesce(sum(current_stock * selling_price), 0) as value'))
                    ->value('value'),
                'lowStock' => Product::active()->lowStock()->count(),
                'outOfStock' => Product::active()->outOfStock()->count(),
                'expiringSoon' => StockBatch::inStock()->expiringWithin($expiryDays)->count(),
                'expired' => StockBatch::inStock()->expired()->count(),
                'suppliers' => Supplier::active()->count(),
            ],
            'expiryDays' => $expiryDays,
            'lowStockProducts' => Product::active()
                ->with(['category', 'unit'])
                ->lowStock()
                ->orderBy('current_stock')
                ->limit(8)
                ->get(),
            'outOfStockProducts' => Product::active()
                ->with(['category', 'unit'])
                ->outOfStock()
                ->orderBy('name')
                ->limit(8)
                ->get(),
            'expiringBatches' => StockBatch::with(['product.unit'])
                ->inStock()
                ->expiringWithin($expiryDays)
                ->orderBy('expires_on')
                ->limit(8)
                ->get(),
            'recentMovements' => StockMovement::with(['product', 'user'])
                ->latestFirst()
                ->limit(10)
                ->get(),
            'recentEntries' => StockEntry::with(['supplier', 'items'])
                ->latest('entry_date')
                ->latest('id')
                ->limit(5)
                ->get(),
            'categoryBreakdown' => Product::query()
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->select('categories.name', DB::raw('count(*) as products'), DB::raw('coalesce(sum(products.current_stock * products.cost_price), 0) as value'))
                ->groupBy('categories.id', 'categories.name')
                ->orderByDesc('value')
                ->limit(8)
                ->get(),
        ]);
    }
}
