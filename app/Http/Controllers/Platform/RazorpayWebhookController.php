<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Services\BillingCycle;
use App\Services\RazorpayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay's own report of what happened: a link paid, or a card on file
 * charged. This is what settles an invoice when nobody is watching a browser.
 */
class RazorpayWebhookController extends Controller
{
    public function __construct(
        protected BillingCycle $billing,
        protected RazorpayGateway $razorpay,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->razorpay->webhookIsGenuine($request->getContent(), $request->header('X-Razorpay-Signature'))) {
            Log::warning('Razorpay webhook rejected: bad signature.');

            return response()->json(['message' => 'Signature mismatch.'], 400);
        }

        $event = $request->string('event')->toString();

        return match ($event) {
            'payment_link.paid', 'payment.captured' => $this->linkPaid($request),
            'subscription.charged' => $this->subscriptionCharged($request),
            'subscription.activated', 'subscription.authenticated' => $this->subscriptionState($request, true),
            'subscription.halted', 'subscription.cancelled', 'subscription.completed', 'subscription.paused' => $this->subscriptionState($request, false),
            // Anything else is acknowledged so Razorpay stops retrying it.
            default => response()->json(['message' => 'Ignored.']),
        };
    }

    protected function linkPaid(Request $request): JsonResponse
    {
        $invoice = $this->resolveInvoice($request);

        if ($invoice === null) {
            Log::warning('Razorpay webhook matched no invoice.');

            return response()->json(['message' => 'No matching invoice.']);
        }

        $this->billing->settleFromGateway($invoice, $this->paymentId($request));

        return response()->json(['message' => 'Recorded.']);
    }

    /**
     * The card on file was charged for a new period.
     */
    protected function subscriptionCharged(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);

        if ($store === null) {
            return response()->json(['message' => 'No matching store.']);
        }

        $store->update(['razorpay_subscription_status' => 'active', 'auto_charge_enabled' => true]);

        $this->billing->settleSubscriptionCharge($store, $this->paymentId($request));

        return response()->json(['message' => 'Recorded.']);
    }

    /**
     * The subscription started, stopped, or failed often enough to be halted.
     */
    protected function subscriptionState(Request $request, bool $active): JsonResponse
    {
        $store = $this->resolveStore($request);

        if ($store === null) {
            return response()->json(['message' => 'No matching store.']);
        }

        $status = (string) ($request->input('payload.subscription.entity.status') ?? ($active ? 'active' : 'stopped'));

        $store->update([
            'auto_charge_enabled' => $active,
            'razorpay_subscription_status' => $status,
        ]);

        return response()->json(['message' => 'Recorded.']);
    }

    protected function resolveStore(Request $request): ?Store
    {
        $subscriptionId = $request->input('payload.subscription.entity.id');

        if (filled($subscriptionId)) {
            return Store::where('razorpay_subscription_id', $subscriptionId)->first();
        }

        $slug = $request->input('payload.subscription.entity.notes.store');

        return filled($slug) ? Store::where('slug', $slug)->first() : null;
    }

    protected function resolveInvoice(Request $request): ?StoreInvoice
    {
        $linkId = $request->input('payload.payment_link.entity.id');

        if (filled($linkId)) {
            return StoreInvoice::where('razorpay_payment_link_id', $linkId)->first();
        }

        // A captured payment carries the invoice number we set as the reference.
        $reference = $request->input('payload.payment.entity.notes.reference_id')
            ?? $request->input('payload.payment_link.entity.reference_id');

        return filled($reference) ? StoreInvoice::where('number', $reference)->first() : null;
    }

    protected function paymentId(Request $request): string
    {
        return (string) (
            $request->input('payload.payment.entity.id')
            ?? $request->input('payload.payment_link.entity.id')
            ?? ''
        );
    }
}
