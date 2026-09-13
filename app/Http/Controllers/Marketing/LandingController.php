<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\DisplayCurrency;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * The public site. Prices come from the same plans the platform bills on,
     * so the page can never quote a price that is no longer charged.
     */
    public function __invoke(): View
    {
        // public(): the free lifetime plan is handed out from the platform and
        // has no business on the pricing table.
        $plans = Plan::query()->active()->public()->ordered()->with('prices')->get();

        return view('marketing.landing', [
            'plans' => $plans,
            'trialDays' => config('tenancy.trial_days'),
            'currency' => DisplayCurrency::current(),
            // What the toggle can promise: the best saving on offer.
            'topDiscount' => (int) round($plans->max(fn (Plan $plan): float => $plan->offersYearly() ? $plan->yearlyDiscount() : 0) ?? 0),
        ]);
    }
}
