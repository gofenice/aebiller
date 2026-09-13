<?php

namespace App\Http\Controllers\Marketing;

use App\Enums\BillingPeriod;
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
        $plans = Plan::query()->active()->ordered()->with('prices')->get();

        return view('marketing.landing', [
            'monthlyPlans' => $plans->where('billing_period', BillingPeriod::Monthly),
            'otherPlans' => $plans->whereIn('billing_period', [BillingPeriod::Yearly, BillingPeriod::Lifetime]),
            'trialDays' => config('tenancy.trial_days'),
            'currency' => DisplayCurrency::current(),
        ]);
    }
}
