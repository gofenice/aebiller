<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\StoreInvoice;
use App\Services\BillingCycle;
use App\Services\PlanLimits;
use App\Services\RazorpayGateway;
use App\Support\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The store's own view of what it owes the platform: reachable at any time,
 * and the only thing reachable once the shop is locked for non-payment.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        protected BillingCycle $billing,
        protected RazorpayGateway $razorpay,
    ) {}

    public function show(PlanLimits $limits): View
    {
        $store = StoreContext::get();

        return view('subscription.show', [
            'store' => $store,
            'invoices' => $store->invoices()->unpaid()->orderBy('due_on')->get(),
            'recentlyPaid' => $store->invoices()->where('status', InvoiceStatus::Paid)->latest('paid_at')->limit(3)->get(),
            'locked' => $store->isLocked(),
            'canPay' => $this->razorpay->enabled(),
            'canAutoCharge' => $this->razorpay->enabled() && $store->billingPeriod()->isRecurring() && $store->plan !== null,
            // Only worth showing where something is actually capped: a panel of
            // four "Unlimited" rows tells the shop nothing.
            'usage' => collect($limits->summary())->reject(fn (array $row): bool => $row['unlimited'])->all(),
        ]);
    }

    /**
     * Send the shopkeeper to Razorpay to pay one invoice.
     */
    public function pay(StoreInvoice $invoice): RedirectResponse
    {
        $this->authoriseInvoice($invoice);

        if ($invoice->isSettled()) {
            return redirect()->route('subscription.show')->with('status', 'That invoice is already paid.');
        }

        try {
            $url = $this->razorpay->paymentLinkFor($invoice, route('subscription.callback'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * Where Razorpay sends the shopkeeper back to. The signature is what makes
     * this trustworthy — never the fact that they arrived here.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->razorpay->callbackIsGenuine($request->all())) {
            return redirect()->route('subscription.show')
                ->with('error', 'That payment could not be confirmed. If money has left your account, contact us and we will check.');
        }

        $invoice = StoreInvoice::where('razorpay_payment_link_id', $request->string('razorpay_payment_link_id')->toString())->first();

        if ($invoice === null || $invoice->store_id !== StoreContext::id()) {
            return redirect()->route('subscription.show')->with('error', 'That payment did not match an invoice for this store.');
        }

        if ($request->string('razorpay_payment_link_status')->toString() !== 'paid') {
            return redirect()->route('subscription.show')->with('error', 'The payment was not completed.');
        }

        $this->billing->settleFromGateway($invoice, $request->string('razorpay_payment_id')->toString());

        return redirect()->route('dashboard')->with('status', "Payment received — thank you. Invoice {$invoice->number} is settled.");
    }

    /**
     * Leave a card with Razorpay so each period is paid without anyone
     * having to remember.
     */
    public function enableAutoCharge(): RedirectResponse
    {
        $this->authorize('manage-subscription');

        try {
            $url = $this->razorpay->startSubscription(StoreContext::get());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->away($url);
    }

    public function disableAutoCharge(): RedirectResponse
    {
        $this->authorize('manage-subscription');

        try {
            $this->razorpay->cancelSubscription(StoreContext::get());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Automatic payment is off. Invoices will need paying one at a time.');
    }

    /**
     * An invoice belongs to one store, and only that store may pay it.
     */
    protected function authoriseInvoice(StoreInvoice $invoice): void
    {
        abort_unless($invoice->store_id === StoreContext::id(), 403);

        $this->authorize('manage-subscription');
    }
}
