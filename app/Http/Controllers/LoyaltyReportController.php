<?php

namespace App\Http\Controllers;

use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoyaltyReportController extends Controller
{
    /**
     * How the programme is doing: points in and out, what the unspent points
     * are worth, and whether members spend more than walk-in shoppers.
     */
    public function __invoke(Request $request): View
    {
        $this->authorize('manage-customers');

        $from = $request->filled('from') ? $request->date('from')->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? $request->date('to')->endOfDay() : now()->endOfDay();
        $settings = LoyaltySetting::current();

        $pointsOf = fn (array $types): int => (int) LoyaltyTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('type', $types)
            ->sum('points');

        $sales = Sale::query()->completed()->whereBetween('sold_at', [$from, $to]);
        $bills = (clone $sales)->count();
        $revenue = (float) (clone $sales)->sum('grand_total');
        $memberBills = (clone $sales)->whereNotNull('customer_id')->count();
        $memberRevenue = (float) (clone $sales)->whereNotNull('customer_id')->sum('grand_total');
        $walkInBills = $bills - $memberBills;

        $outstanding = (int) Customer::where('points_balance', '>', 0)->sum('points_balance');

        return view('reports.loyalty', [
            'from' => $from,
            'to' => $to,
            'settings' => $settings,
            'stats' => [
                'members' => Customer::count(),
                'newMembers' => Customer::whereBetween('created_at', [$from, $to])->count(),
                'activeMembers' => Customer::where('last_visit_at', '>=', now()->subDays(90))->count(),
                'issued' => $pointsOf(LoyaltyTransactionType::issuing()),
                'redeemed' => -$pointsOf([LoyaltyTransactionType::Redeem]) - $pointsOf([LoyaltyTransactionType::RedeemRefund]),
                'expired' => -$pointsOf([LoyaltyTransactionType::Expiry]),
                'adjusted' => $pointsOf([LoyaltyTransactionType::Adjustment]),
                'outstanding' => $outstanding,
                'liability' => $settings->pointsWorth($outstanding),
                'expiringSoon' => (int) LoyaltyTransaction::query()->expiringBy(now()->addDays(30))->sum('points_remaining'),
                'discountGiven' => (float) (clone $sales)->sum('loyalty_discount'),
                'memberShare' => $revenue > 0 ? round($memberRevenue / $revenue * 100, 1) : 0.0,
                'memberBills' => $memberBills,
                'bills' => $bills,
                'memberBasket' => $memberBills > 0 ? round($memberRevenue / $memberBills, 2) : 0.0,
                'walkInBasket' => $walkInBills > 0 ? round(($revenue - $memberRevenue) / $walkInBills, 2) : 0.0,
            ],
            'tiers' => LoyaltyTier::ordered()->withCount('customers')->get(),
            'topMembers' => Customer::query()
                ->with('tier')
                ->withSum(['sales as period_spend' => fn ($query) => $query->completed()->whereBetween('sold_at', [$from, $to])], 'grand_total')
                ->withCount(['sales as period_visits' => fn ($query) => $query->completed()->whereBetween('sold_at', [$from, $to])])
                ->orderByDesc('period_spend')
                ->limit(10)
                ->get()
                ->filter(fn (Customer $customer): bool => (float) $customer->period_spend > 0),
        ]);
    }
}
