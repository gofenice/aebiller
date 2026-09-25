<?php

namespace App\Http\Controllers\Platform;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SaveStoreRequest;
use App\Models\Plan;
use App\Models\Store;
use App\Services\StoreProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function __construct(protected StoreProvisioner $provisioner) {}

    public function index(Request $request): View
    {
        $monthStart = now()->startOfMonth();

        return view('platform.stores.index', [
            'stores' => Store::query()
                ->when($request->string('status')->toString() === 'archived', fn ($query) => $query->onlyTrashed())
                ->withCount(['users', 'products'])
                ->withCount(['sales as bills_this_month' => fn ($query) => $query->completed()->where('sold_at', '>=', $monthStart)])
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $term = $request->string('search')->toString();
                    $query->where(fn ($query) => $query
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%")
                        ->orWhere('owner_email', 'like', "%{$term}%"));
                })
                ->when(
                    $request->filled('status') && $request->string('status')->toString() !== 'archived',
                    fn ($query) => $query->where('status', $request->string('status')->toString()),
                )
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => StoreStatus::cases(),
            'archivedCount' => Store::onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        $defaults = config('tenancy.defaults');

        return view('platform.stores.form', [
            'store' => new Store([
                'currency_code' => $defaults['currency_code'],
                'currency_symbol' => $defaults['currency_symbol'],
                'timezone' => $defaults['timezone'],
                'expiry_alert_days' => $defaults['expiry_alert_days'],
                'billing_day' => 1,
                'grace_days' => 7,
                'invoice_lead_days' => 7,
                'auto_suspend' => true,
                'prorate_first_invoice' => true,
                // Billing starts today; the part month to the next billing day
                // is what the first invoice charges.
                'billing_starts_on' => today(),
                'next_invoice_on' => today(),
            ]),
            'plans' => Plan::ordered()->get(),
        ]);
    }

    /**
     * Create the store, its first owner account and enough starter data for
     * the shop to be usable the moment they sign in.
     */
    public function store(SaveStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $store = Store::create([
            ...Arr::except($validated, ['admin_name', 'admin_email', 'admin_password']),
            'status' => StoreStatus::Active,
            'tax_rates' => config('tenancy.defaults.tax_rates'),
        ]);

        $account = $this->provisioner->provision($store, [
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => $validated['admin_password'] ?? null,
        ]);

        return redirect()
            ->route('platform.stores.show', $store)
            // Shown once, so it can be handed to the shop owner.
            ->with('new_account', ['email' => $account['user']->email, 'password' => $account['password']])
            ->with('status', "{$store->name} is live at {$store->host()}.");
    }

    public function show(Store $store): View
    {
        $monthStart = now()->startOfMonth();

        $store->loadCount(['users', 'products', 'customers'])
            ->loadCount(['sales as bills_this_month' => fn ($query) => $query->completed()->where('sold_at', '>=', $monthStart)]);

        return view('platform.stores.show', [
            'store' => $store->load('plan'),
            'staff' => $store->users()->orderBy('name')->get(),
            'invoices' => $store->invoices()->latest('period_start')->limit(12)->get(),
            'outstanding' => (float) $store->invoices()->unpaid()->sum(DB::raw('amount - amount_paid')),
            'newAccount' => session('new_account'),
        ]);
    }

    public function edit(Store $store): View
    {
        return view('platform.stores.form', [
            'store' => $store,
            'plans' => Plan::ordered()->get(),
        ]);
    }

    public function update(SaveStoreRequest $request, Store $store): RedirectResponse
    {
        $store->update(Arr::except($request->validated(), ['admin_name', 'admin_email', 'admin_password']));

        return redirect()->route('platform.stores.show', $store)->with('status', "{$store->name} was updated.");
    }

    /**
     * Close a store — the usual reason is an unpaid month. Its data is kept.
     */
    public function suspend(Request $request, Store $store): RedirectResponse
    {
        $reason = $request->validate([
            'suspension_reason' => ['nullable', 'string', 'max:200'],
        ])['suspension_reason'] ?? 'Monthly payment overdue';

        $store->update([
            'status' => StoreStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);

        return back()->with('status', "{$store->name} is suspended. Staff see a notice instead of the till.");
    }

    public function reactivate(Store $store): RedirectResponse
    {
        $store->update([
            'status' => StoreStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        return back()->with('status', "{$store->name} is open again.");
    }

    /**
     * Archive a store: it stops answering and gives up its subdomain, but its
     * data is kept long enough for a mistake to be undone.
     */
    public function destroy(Store $store): RedirectResponse
    {
        $name = $store->name;
        $store->archive();

        return redirect()
            ->route('platform.stores.index', ['status' => 'archived'])
            ->with('status', sprintf(
                '%s is archived. Its data is kept until %s, and the address %s is free again.',
                $name,
                $store->purgeableOn()?->format('d M Y'),
                $store->original_slug,
            ));
    }

    /**
     * Put an archived store back.
     */
    public function restore(string $store): RedirectResponse
    {
        $archived = Store::onlyTrashed()->where('id', $store)->firstOrFail();

        $tookOldAddress = $archived->unarchive();

        return redirect()->route('platform.stores.show', $archived)->with(
            $tookOldAddress ? 'status' : 'error',
            $tookOldAddress
                ? "{$archived->name} is back, at {$archived->slug}."
                : "{$archived->name} is back, but another store has taken its old address — it is at {$archived->slug} for now.",
        );
    }

    /**
     * Delete an archived store and everything it owns. There is no undoing
     * this, so the address has to be typed out to confirm.
     */
    public function purge(Request $request, string $store): RedirectResponse
    {
        $archived = Store::onlyTrashed()->where('id', $store)->firstOrFail();
        $address = $archived->original_slug ?: $archived->slug;

        $request->validate(
            ['confirm' => ['required', 'string']],
            ['confirm.required' => 'Type the address to confirm.'],
        );

        if ($request->string('confirm')->toString() !== $address) {
            return back()->with('error', "That is not the address of this store. Type {$address} to confirm.");
        }

        $name = $archived->name;
        $archived->forceDelete();

        return redirect()
            ->route('platform.stores.index')
            ->with('status', "{$name} and all of its data have been deleted.");
    }
}
