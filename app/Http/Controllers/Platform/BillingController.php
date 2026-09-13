<?php

namespace App\Http\Controllers\Platform;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\RecordPaymentRequest;
use App\Mail\SubscriptionInvoiceRaised;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Services\BillingCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class BillingController extends Controller
{
    public function __construct(protected BillingCycle $billing) {}

    /**
     * Every subscription invoice, and what is still owed.
     */
    public function index(Request $request): View
    {
        $query = StoreInvoice::query()
            ->with(['store', 'plan'])
            ->when($request->filled('store'), fn ($q) => $q->whereHas('store', fn ($q) => $q->where('slug', $request->string('store')->toString())))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('period_start', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('period_start', '<=', $request->date('to')));

        // Money is only ever added up within one currency.
        $byCurrency = (clone $query)
            ->select(
                'currency_code',
                DB::raw('count(*) as invoices'),
                DB::raw('coalesce(sum(amount), 0) as billed'),
                DB::raw('coalesce(sum(amount_paid), 0) as collected'),
            )
            ->groupBy('currency_code')
            ->get();

        return view('platform.billing.index', [
            'invoices' => $query->latest('period_start')->latest('id')->paginate(25)->withQueryString(),
            'byCurrency' => $byCurrency,
            'overdueCount' => StoreInvoice::overdue()->count(),
            'statuses' => InvoiceStatus::cases(),
            'stores' => Store::orderBy('name')->get(['slug', 'name']),
        ]);
    }

    public function show(StoreInvoice $invoice): View
    {
        $invoice->load(['store', 'plan', 'payments.recorder']);

        return view('platform.billing.show', [
            'invoice' => $invoice,
            // Online payments arrive through Razorpay; this form is for money
            // that came in by hand.
            'methods' => PaymentMethodType::manual(),
        ]);
    }

    /**
     * Record money received against an invoice.
     */
    public function pay(RecordPaymentRequest $request, StoreInvoice $invoice): RedirectResponse
    {
        try {
            $this->billing->recordPayment($invoice, $request->validated(), Auth::guard('platform')->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $invoice->refresh();

        return back()->with('status', sprintf(
            'Payment recorded. %s',
            $invoice->outstanding() > 0
                ? $invoice->currency_code.' '.number_format($invoice->outstanding(), 2).' still owed.'
                : "Invoice {$invoice->number} is settled.",
        ));
    }

    /**
     * Raise whatever is due now, rather than waiting for tonight's run.
     */
    public function generate(): RedirectResponse
    {
        $result = $this->billing->generateDueInvoices();

        return back()->with('status', $result['invoices'] > 0
            ? "Raised {$result['invoices']} invoice(s)."
            : 'Nothing is due to be invoiced today.');
    }

    /**
     * Send the invoice to the shop again — asked for when an owner says they
     * never saw it.
     */
    public function notify(StoreInvoice $invoice): RedirectResponse
    {
        $recipient = $invoice->store?->owner_email ?: $invoice->store?->email;

        if (blank($recipient)) {
            return back()->with('error', 'That store has no email address on file.');
        }

        $this->billing->email($invoice->store, new SubscriptionInvoiceRaised($invoice), "invoice {$invoice->number}");

        return back()->with('status', "Invoice {$invoice->number} was emailed to {$recipient}.");
    }

    /**
     * Cancel an invoice that should never have been raised.
     */
    public function void(StoreInvoice $invoice): RedirectResponse
    {
        $invoice->update(['status' => InvoiceStatus::Void]);

        return back()->with('status', "Invoice {$invoice->number} was cancelled.");
    }
}
