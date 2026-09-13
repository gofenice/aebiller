<?php

namespace App\Http\Controllers\Platform;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Models\StorePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The platform overview: how many stores there are, how busy they are,
     * and which ones need attention.
     *
     * Store data is read with no store in context, so these figures deliberately
     * span every store.
     */
    public function __invoke(): View
    {
        $monthStart = now()->startOfMonth();

        return view('platform.dashboard', [
            'stats' => [
                'stores' => Store::count(),
                'active' => Store::where('status', StoreStatus::Active)->count(),
                'suspended' => Store::where('status', StoreStatus::Suspended)->count(),
                'newThisMonth' => Store::where('created_at', '>=', $monthStart)->count(),
                'billsThisMonth' => Sale::completed()->where('sold_at', '>=', $monthStart)->count(),
                'overdueStores' => StoreInvoice::overdue()->distinct()->count('store_id'),
            ],
            // Money is only ever totalled within one currency.
            'outstanding' => StoreInvoice::query()
                ->unpaid()
                ->select('currency_code', DB::raw('coalesce(sum(amount - amount_paid), 0) as total'))
                ->groupBy('currency_code')
                ->get(),
            'collectedThisMonth' => StorePayment::query()
                ->whereDate('received_on', '>=', $monthStart)
                ->select('currency_code', DB::raw('coalesce(sum(amount), 0) as total'))
                ->groupBy('currency_code')
                ->get(),
            'stores' => Store::query()
                ->withCount(['users', 'products'])
                ->withCount(['sales as bills_this_month' => fn ($query) => $query->completed()->where('sold_at', '>=', $monthStart)])
                ->withMax('sales as last_sale_at', 'sold_at')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
