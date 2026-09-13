<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BillingPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SavePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('platform.plans.index', [
            'plans' => Plan::query()->withCount('stores')->with('prices')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('platform.plans.form', [
            'plan' => new Plan([
                'currency_code' => config('tenancy.base_currency'),
                'billing_period' => BillingPeriod::Monthly,
                'yearly_discount_percent' => 20,
                'is_active' => true,
                'is_public' => true,
                'is_free' => false,
            ]),
        ]);
    }

    public function store(SavePlanRequest $request): RedirectResponse
    {
        $plan = Plan::create(Arr::except($request->validated(), 'prices'));

        $this->syncPrices($plan, $request->validated('prices') ?? []);

        return redirect()->route('platform.plans.index')->with('status', "Plan \"{$plan->name}\" was added.");
    }

    public function edit(Plan $plan): View
    {
        return view('platform.plans.form', ['plan' => $plan->load('prices')]);
    }

    /**
     * Changing a price changes what future invoices charge; invoices already
     * raised keep the amount they were raised at.
     */
    public function update(SavePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update(Arr::except($request->validated(), 'prices'));

        $this->syncPrices($plan, $request->validated('prices') ?? []);

        return redirect()->route('platform.plans.index')->with('status', "Plan \"{$plan->name}\" was updated.");
    }

    /**
     * Store a price for each currency that was given one, and drop the rest —
     * a blank box means "charge the base price in this currency's place".
     *
     * @param  array<string, mixed>  $prices
     */
    protected function syncPrices(Plan $plan, array $prices): void
    {
        foreach (config('tenancy.pricing_currencies') as $code) {
            if ($code === $plan->currency_code) {
                continue;
            }

            $amount = $prices[$code] ?? null;

            if (blank($amount)) {
                $plan->prices()->where('currency_code', $code)->delete();

                continue;
            }

            $plan->prices()->updateOrCreate(['currency_code' => $code], ['amount' => $amount]);
        }
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->stores()->exists()) {
            return back()->with('error', "\"{$plan->name}\" is in use by a store. Switch those stores first, or mark it inactive.");
        }

        $plan->delete();

        return back()->with('status', "Plan \"{$plan->name}\" was deleted.");
    }
}
