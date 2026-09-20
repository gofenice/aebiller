<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustPointsRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\Sale;
use App\Services\BarcodeGenerator;
use App\Services\LoyaltyService;
use App\Services\PlanLimits;
use App\Services\QrCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CustomerController extends Controller
{
    public function __construct(protected LoyaltyService $loyalty) {}

    /**
     * Loyalty members, searchable by name, mobile or card number.
     */
    public function index(Request $request): View
    {
        $this->authorize('manage-customers');

        $status = $request->string('status')->toString();

        $customers = Customer::query()
            ->with(['tier', 'activeCard'])
            // What each member still owes on their credit bills, alongside
            // any balance carried over from the shop's old book.
            ->withSum(['sales as bills_due' => fn ($query) => $query->outstanding()], 'amount_outstanding')
            ->when($request->filled('search'), fn ($query) => $query->search($request->string('search')->toString()))
            ->when($request->filled('tier'), fn ($query) => $query->where('loyalty_tier_id', $request->integer('tier')))
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'on_hold', fn ($query) => $query->where('is_active', false))
            // Members who have not shopped for 90 days — the ones worth a call.
            ->when($status === 'owing', fn ($query) => $query->owing())
            ->when($status === 'lapsed', fn ($query) => $query->active()->where(fn ($query) => $query
                ->whereNull('last_visit_at')
                ->orWhere('last_visit_at', '<', now()->subDays(90))))
            ->when($request->string('sort')->toString(), function ($query, string $sort): void {
                match ($sort) {
                    'points' => $query->orderByDesc('points_balance'),
                    'spend' => $query->orderByDesc('lifetime_spend'),
                    'recent' => $query->orderByDesc('last_visit_at'),
                    'newest' => $query->latest('id'),
                    default => $query->orderBy('name'),
                };
            }, fn ($query) => $query->orderBy('name'))
            ->paginate(25)
            ->withQueryString();

        $settings = LoyaltySetting::current();
        $outstanding = (int) Customer::where('points_balance', '>', 0)->sum('points_balance');

        return view('customers.index', [
            'customers' => $customers,
            'tiers' => LoyaltyTier::ordered()->withCount('customers')->get(),
            'settings' => $settings,
            'totals' => [
                'members' => Customer::count(),
                'active' => Customer::where('last_visit_at', '>=', now()->subDays(90))->count(),
                'newThisMonth' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
                'points' => $outstanding,
                'liability' => $settings->pointsWorth($outstanding),
                'due' => (float) Customer::sum('opening_due_outstanding') + (float) Sale::outstanding()->sum('amount_outstanding'),
                'openingDue' => (float) Customer::sum('opening_due_outstanding'),
                'owingMembers' => Customer::owing()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-customers');

        return view('customers.form', [
            'customer' => new Customer(['is_active' => true, 'marketing_opt_in' => false]),
            'settings' => LoyaltySetting::current(),
        ]);
    }

    /**
     * Enrol: the member gets a card number and the welcome bonus straight away.
     */
    public function store(StoreCustomerRequest $request, PlanLimits $limits): RedirectResponse
    {
        if ($blocked = $limits->reasonToBlock('max_customers')) {
            return back()->withInput()->with('error', $blocked);
        }

        $customer = $this->loyalty->enrol($request->validated(), $request->user());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', "{$customer->name} has joined with card {$customer->activeCard->formattedNumber()}. Print the card from here.");
    }

    /**
     * The member's profile: card, points, history and bills.
     */
    public function show(Customer $customer, BarcodeGenerator $barcodes, QrCodeGenerator $qr): View
    {
        $this->authorize('manage-customers');

        $customer->load(['tier', 'activeCard', 'cards.issuer', 'enroller']);

        return view('customers.show', [
            'customer' => $customer,
            'settings' => LoyaltySetting::current(),
            'summary' => $this->loyalty->memberSummary($customer),
            'transactions' => $customer->transactions()
                ->with(['sale', 'user'])
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'recentSales' => $customer->sales()->latest('sold_at')->limit(8)->get(),
            'barcodeSvg' => $customer->activeCard !== null ? $barcodes->ean13($customer->activeCard->number, 28) : null,
            'qrSvg' => $qr->svg($customer->publicUrl(), 120),
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('manage-customers');

        return view('customers.form', [
            'customer' => $customer,
            'settings' => LoyaltySetting::current(),
        ]);
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer)->with('status', "{$customer->name}'s details were updated.");
    }

    /**
     * Only a member who has never been billed can be deleted; anyone else is
     * part of the sales record and is put on hold instead.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete-records');

        if ($customer->sales()->exists()) {
            return back()->with('error', "{$customer->name} has bills on record. Put the membership on hold instead.");
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('status', "{$customer->name} was deleted.");
    }

    /**
     * A goodwill credit or a correction. Super admins only.
     */
    public function adjustPoints(AdjustPointsRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $this->loyalty->adjust($customer, $request->integer('points'), $request->string('reason')->toString(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', sprintf(
            '%s %s points. New balance: %s.',
            $request->integer('points') > 0 ? 'Added' : 'Took away',
            number_format(abs($request->integer('points'))),
            number_format($customer->fresh()->points_balance),
        ));
    }

    /**
     * Swap a lost or damaged card for a new number; the old one stops scanning.
     */
    public function replaceCard(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('manage-customers');

        $validated = $request->validate([
            'reason' => ['required', 'in:lost,damaged'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $card = $this->loyalty->replaceCard($customer, $request->user(), $validated['reason'] === 'lost', $validated['note'] ?? null);

        return back()->with('status', "New card {$card->formattedNumber()} issued — the old card will no longer scan. Print the new one below.");
    }
}
