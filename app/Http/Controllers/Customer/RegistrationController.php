<?php

namespace App\Http\Controllers\Customer;

use App\Enums\BillingPeriod;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RegisterStoreRequest;
use App\Models\Plan;
use App\Models\Store;
use App\Services\StoreProvisioner;
use App\Support\DisplayCurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Signing a shop up. It gets its own address and a free trial, and is usable
 * the moment the form is submitted.
 */
class RegistrationController extends Controller
{
    public function __construct(protected StoreProvisioner $provisioner) {}

    public function create(Request $request): View
    {
        return view('customer.register', [
            'plans' => Plan::query()->active()->public()->ordered()->with('prices')->get(),
            'chosenPlan' => $request->string('plan')->toString(),
            'chosenPeriod' => $request->string('period')->toString() === 'yearly' ? 'yearly' : 'monthly',
            'trialDays' => config('tenancy.trial_days'),
            'currency' => DisplayCurrency::current(),
        ]);
    }

    /**
     * Is this address free? Asked as the shopkeeper types.
     */
    public function availability(Request $request): JsonResponse
    {
        $slug = Str::slug(Str::lower($request->string('slug')->toString()));

        if (strlen($slug) < 3) {
            return response()->json(['slug' => $slug, 'available' => false, 'reason' => 'Too short']);
        }

        if (in_array($slug, config('tenancy.reserved_subdomains'), true)) {
            return response()->json(['slug' => $slug, 'available' => false, 'reason' => 'Reserved']);
        }

        $taken = Store::where('slug', $slug)->exists();

        return response()->json([
            'slug' => $slug,
            'available' => ! $taken,
            'reason' => $taken ? 'Already taken' : 'Available',
            'host' => $slug.'.'.config('tenancy.central_domain'),
        ]);
    }

    /**
     * Create the shop, its owner account and its starter data, and start the
     * free trial. The first invoice falls due the day the trial ends.
     */
    public function store(RegisterStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $plan = filled($validated['plan'] ?? null) ? Plan::where('slug', $validated['plan'])->first() : null;

        // Never leave a shop on no plan at all: that is a zero fee, which is
        // never invoiced, which is a free account nobody meant to give away.
        $plan ??= Plan::query()->active()->public()->ordered()->first();

        // Yearly only where the plan actually offers it, so a hand-edited form
        // cannot put a shop on a period it is not priced for.
        $period = ($validated['period'] ?? null) === 'yearly' && $plan?->offersYearly()
            ? BillingPeriod::Yearly
            : ($plan?->billing_period ?? BillingPeriod::Monthly);
        // One currency throughout: what the shop was quoted is what it trades
        // in and what it is billed in.
        $currencyCode = DisplayCurrency::current();
        $trialEndsOn = today()->addDays(config('tenancy.trial_days'));

        $store = Store::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'status' => StoreStatus::Active,
            'owner_name' => $validated['owner_name'],
            'owner_email' => $validated['owner_email'],
            'owner_phone' => $validated['owner_phone'] ?? null,
            'currency_code' => $currencyCode,
            'currency_symbol' => DisplayCurrency::symbolOf($currencyCode),
            'timezone' => $validated['timezone'],
            'expiry_alert_days' => config('tenancy.defaults.expiry_alert_days'),
            'tax_rates' => config('tenancy.defaults.tax_rates'),
            'plan_id' => $plan?->id,

            // Billed in the currency and on the period the prices were quoted
            // in, so the first invoice is for the figure they agreed to.
            'billing_currency' => $currencyCode,
            'billing_period' => $period,

            // Free until the trial ends; the first invoice is due that day.
            'trial_ends_on' => $trialEndsOn,
            'billing_starts_on' => $trialEndsOn,
            'next_invoice_on' => $trialEndsOn,
            'billing_day' => 1,
            // A three-day trial cannot be told a week ahead, so: the day before.
            'invoice_lead_days' => 1,
            'grace_days' => 0,
            'prorate_first_invoice' => true,
            'auto_suspend' => true,
        ]);

        $this->provisioner->provision($store, [
            'name' => $validated['owner_name'],
            'email' => $validated['owner_email'],
            'password' => $validated['password'],
        ]);

        return redirect()->route('register.welcome', $store);
    }

    /**
     * "Your shop is ready" — with a link that signs the owner straight in.
     */
    public function welcome(Store $store): View
    {
        $owner = $store->users()->withoutGlobalScopes()->orderBy('id')->first();

        return view('customer.registered', [
            'store' => $store,
            'trialEndsOn' => $store->trial_ends_on,
            // Good for an hour, and only for this shop's own owner.
            'signInUrl' => $owner !== null
                ? URL::temporarySignedRoute('store.welcome', now()->addHour(), [
                    'store' => $store->slug,
                    'user' => $owner->id,
                ])
                : $store->url().'/login',
        ]);
    }
}
