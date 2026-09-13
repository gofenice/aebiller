<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltyCard;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Services\BarcodeGenerator;
use App\Services\LoyaltyService;
use App\Services\QrCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Print-ready loyalty cards. Two layouts: an A4 sheet with each card's front
 * and back side by side for cutting out and laminating, and exact CR80 pages
 * (the size of a bank card) for a plastic card printer.
 */
class LoyaltyCardController extends Controller
{
    public function __construct(
        protected BarcodeGenerator $barcodes,
        protected QrCodeGenerator $qr,
    ) {}

    /**
     * One member's card.
     */
    public function show(Request $request, Customer $customer): View|RedirectResponse
    {
        $this->authorize('manage-customers');

        $customer->load(['tier', 'activeCard']);

        if ($customer->activeCard === null) {
            return back()->with('error', "{$customer->name} has no active card — issue a new card first.");
        }

        return view('customers.cards', $this->sheetFor(collect([$customer]), $request, "Card for {$customer->name}"));
    }

    /**
     * A print run: the members ticked on the list, or everyone on a tier or
     * who joined since a date.
     */
    public function sheet(Request $request): View
    {
        $this->authorize('manage-customers');

        $picked = array_filter(array_map('intval', (array) $request->input('customers', [])));

        $customers = Customer::query()
            ->with(['tier', 'activeCard'])
            ->whereHas('activeCard')
            ->when($picked !== [], fn ($query) => $query->whereKey($picked), fn ($query) => $query->active())
            ->when($request->filled('tier'), fn ($query) => $query->where('loyalty_tier_id', $request->integer('tier')))
            ->when($request->filled('since'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('since')))
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('customers.cards', $this->sheetFor($customers, $request, 'Loyalty cards'));
    }

    /**
     * One sample card per tier, so the designs can be seen and test-printed
     * before any member has joined.
     */
    public function designs(Request $request): View
    {
        $this->authorize('manage-customers');

        $samples = LoyaltyTier::ordered()->get()->values()->map(function (LoyaltyTier $tier, int $index): Customer {
            $body = LoyaltyService::CARD_PREFIX.str_pad((string) ($index + 1), 10, '0', STR_PAD_LEFT);

            $customer = new Customer(['name' => 'Aisha Rahman', 'uuid' => (string) Str::uuid()]);
            $customer->id = -1 - $index;
            $customer->created_at = now();
            $customer->setRelation('tier', $tier);
            $customer->setRelation('activeCard', new LoyaltyCard([
                'number' => $body.$this->barcodes->checkDigit($body),
                'issued_at' => now(),
            ]));

            return $customer;
        });

        return view('customers.cards', $this->sheetFor($samples, $request, 'Card designs', isSample: true));
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return array<string, mixed>
     */
    protected function sheetFor(Collection $customers, Request $request, string $title, bool $isSample = false): array
    {
        return [
            'title' => $title,
            'customers' => $customers,
            'isSample' => $isSample,
            'layout' => $request->string('layout')->toString() === 'pvc' ? 'pvc' : 'a4',
            'settings' => LoyaltySetting::current(),
            'barcodes' => $customers->mapWithKeys(fn (Customer $customer): array => [
                $customer->id => $this->barcodes->ean13($customer->activeCard->number, 28),
            ]),
            // A sample card has no balance page to open, so its QR points at the store.
            'qrCodes' => $customers->mapWithKeys(fn (Customer $customer): array => [
                $customer->id => $this->qr->svg($isSample ? url('/') : $customer->publicUrl(), 120),
            ]),
        ];
    }
}
