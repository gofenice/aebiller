<?php

namespace App\Services;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethodType;
use App\Enums\StoreStatus;
use App\Mail\SubscriptionInvoiceRaised;
use App\Mail\SubscriptionOverdue;
use App\Mail\SubscriptionPaymentReceived;
use App\Models\PlatformUser;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Models\StorePayment;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * The money side of the platform: raising each store's invoice, recording what
 * they pay, telling them about it, and closing stores that stop paying.
 *
 * Nothing here is scoped to a store — these are the platform's own records.
 */
class BillingCycle
{
    /**
     * Raise the invoices that have come due, including those being raised
     * early so a shop is told before the money is owed. Runs nightly.
     *
     * @return array{invoices: int, total: array<string, float>}
     */
    public function generateDueInvoices(?CarbonInterface $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?? now())->startOfDay();
        $raised = [];

        // Each store is told a set number of days before its renewal date, so
        // the net is cast wide here and narrowed per store below.
        $stores = Store::query()
            ->with('plan')
            ->whereNotNull('next_invoice_on')
            ->whereDate('next_invoice_on', '<=', $asOf->copy()->addDays(90))
            ->get()
            ->filter(fn (Store $store): bool => $asOf->greaterThanOrEqualTo(
                $store->next_invoice_on->copy()->subDays($store->invoiceLeadDays()),
            ));

        foreach ($stores as $store) {
            $invoice = $this->raiseInvoice($store, $store->next_invoice_on, $asOf);

            if ($invoice !== null) {
                $raised[] = $invoice;
            }
        }

        $totals = [];

        foreach ($raised as $invoice) {
            $totals[$invoice->currency_code] = round(($totals[$invoice->currency_code] ?? 0) + (float) $invoice->amount, 2);
        }

        return ['invoices' => count($raised), 'total' => $totals];
    }

    /**
     * The invoice for one store's period, and the store's next renewal date
     * moved on. A store with nothing to pay is skipped.
     *
     * The invoice is dated today but falls due on the renewal date, which is
     * how a shop gets told before the money is actually owed.
     */
    public function raiseInvoice(Store $store, CarbonInterface $renewalDate, ?CarbonInterface $issuedOn = null): ?StoreInvoice
    {
        $fee = $store->periodFee();
        $renewalDate = Carbon::parse($renewalDate)->startOfDay();
        $issuedOn = Carbon::parse($issuedOn ?? now())->startOfDay();
        $period = $store->billingPeriod();

        if ($fee <= 0) {
            // Nothing to bill — free or not yet priced. Move the date on so it
            // is not looked at again tomorrow.
            $store->update(['next_invoice_on' => $period->nextRenewal($renewalDate, $store->billing_day)]);

            return null;
        }

        $charge = $this->chargeFor($store, $renewalDate, $period, $fee);

        $invoice = DB::transaction(function () use ($store, $renewalDate, $issuedOn, $charge): ?StoreInvoice {
            $existing = StoreInvoice::where('store_id', $store->id)
                ->whereDate('period_start', $renewalDate)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $store->update(['next_invoice_on' => $charge['next_renewal']]);

                return null;
            }

            $invoice = StoreInvoice::create([
                'store_id' => $store->id,
                'plan_id' => $store->plan_id,
                'number' => $this->nextNumber($renewalDate),
                'period_start' => $renewalDate,
                'period_end' => $charge['period_end'],
                'amount' => $charge['amount'],
                'is_prorated' => $charge['prorated'],
                // billedCurrency(), not billingCurrency(): the amount above may
                // have fallen back to the plan's base price, and an invoice that
                // charges 39 while saying SAR is quietly wrong.
                'currency_code' => $store->billedCurrency(),
                'status' => InvoiceStatus::Issued,
                'issued_on' => $issuedOn->lessThan($renewalDate) ? $issuedOn : $renewalDate,
                'due_on' => $renewalDate,
                'notes' => $charge['note'],
            ]);

            // A lifetime licence is billed once: there is no next renewal.
            $store->update(['next_invoice_on' => $charge['next_renewal']]);

            return $invoice;
        });

        if ($invoice !== null) {
            $this->email($store, new SubscriptionInvoiceRaised($invoice), "invoice {$invoice->number}");
        }

        return $invoice;
    }

    /**
     * What to charge for this period, and where the period ends.
     *
     * A shop joining part-way through a month pays only for the days left,
     * so its first bill is small and every one after it is a whole month.
     * Yearly and lifetime have no fixed cycle to align to, so they start
     * their period on the day they join and pay in full.
     *
     * @return array{amount: float, period_end: Carbon|null, next_renewal: Carbon|null, prorated: bool, note: string|null}
     */
    protected function chargeFor(Store $store, Carbon $renewalDate, BillingPeriod $period, float $fee): array
    {
        $full = [
            'amount' => $fee,
            'period_end' => $period->periodEnd($renewalDate),
            'next_renewal' => $period->nextRenewal($renewalDate, $store->billing_day),
            'prorated' => false,
            'note' => null,
        ];

        if ($period !== BillingPeriod::Monthly || ! $store->prorate_first_invoice) {
            return $full;
        }

        // Only the very first invoice can be a part period.
        if ($store->invoices()->exists()) {
            return $full;
        }

        $nextBillingDay = $this->nextBillingDay($renewalDate, $store->billing_day);
        $fullDays = (int) round($nextBillingDay->copy()->subMonthNoOverflow()->diffInDays($nextBillingDay));
        $chargeDays = (int) round($renewalDate->diffInDays($nextBillingDay));

        if ($chargeDays <= 0 || $chargeDays >= $fullDays) {
            return $full;
        }

        return [
            'amount' => round($fee * $chargeDays / $fullDays, 2),
            'period_end' => $nextBillingDay->copy()->subDay(),
            'next_renewal' => $nextBillingDay,
            'prorated' => true,
            'note' => "Part month: {$chargeDays} of {$fullDays} days, from ".$renewalDate->format('d M Y').'.',
        ];
    }

    /**
     * The next time the billing day comes round after the given date.
     */
    protected function nextBillingDay(Carbon $from, ?int $billingDay): Carbon
    {
        $day = max($billingDay ?: 1, 1);
        $candidate = $from->copy()->setDay(min($day, $from->daysInMonth));

        if ($candidate->lessThanOrEqualTo($from)) {
            $candidate = $from->copy()->addMonthNoOverflow();
            $candidate->setDay(min($day, $candidate->daysInMonth));
        }

        return $candidate->startOfDay();
    }

    /**
     * Settle an invoice from a gateway payment. Safe to call twice — Razorpay
     * reports a payment by both callback and webhook, and may retry either.
     */
    public function settleFromGateway(StoreInvoice $invoice, string $paymentId): ?StorePayment
    {
        $alreadyRecorded = StorePayment::where('store_invoice_id', $invoice->id)
            ->where('reference', $paymentId)
            ->exists();

        if ($alreadyRecorded || $invoice->isSettled()) {
            return null;
        }

        $payment = $this->recordPayment($invoice, [
            'amount' => $invoice->outstanding(),
            'method' => PaymentMethodType::Razorpay->value,
            'reference' => $paymentId,
            'received_on' => now()->toDateString(),
            'notes' => 'Paid online through Razorpay.',
        ]);

        $invoice->update(['razorpay_payment_id' => $paymentId]);

        return $payment;
    }

    /**
     * Razorpay charged the card it has on file. There may be no invoice yet —
     * it charges on its own schedule — so one is raised to put it against.
     */
    public function settleSubscriptionCharge(Store $store, string $paymentId): ?StorePayment
    {
        // Razorpay retries a webhook it thinks was missed. Without this, a
        // second delivery would raise an invoice for the next period and mark
        // that paid too.
        $alreadyRecorded = StorePayment::where('store_id', $store->id)
            ->where('reference', $paymentId)
            ->exists();

        if ($alreadyRecorded) {
            return null;
        }

        $invoice = $store->invoices()->unpaid()->orderBy('due_on')->first();

        if ($invoice === null) {
            $this->raiseInvoice($store, $store->next_invoice_on ?? today());

            $invoice = $store->invoices()->unpaid()->orderBy('due_on')->first();
        }

        return $invoice !== null ? $this->settleFromGateway($invoice, $paymentId) : null;
    }

    /**
     * Record money received. Paying off everything owed reopens a store that
     * was closed for non-payment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordPayment(StoreInvoice $invoice, array $attributes, ?PlatformUser $admin = null): StorePayment
    {
        $amount = round((float) $attributes['amount'], 2);

        if ($amount <= 0) {
            throw new RuntimeException('A payment has to be more than zero.');
        }

        $payment = DB::transaction(function () use ($invoice, $attributes, $admin, $amount): StorePayment {
            $payment = StorePayment::create([
                'store_id' => $invoice->store_id,
                'store_invoice_id' => $invoice->id,
                'amount' => $amount,
                'currency_code' => $invoice->currency_code,
                'method' => $attributes['method'],
                'reference' => $attributes['reference'] ?? null,
                'received_on' => $attributes['received_on'] ?? now()->toDateString(),
                'notes' => $attributes['notes'] ?? null,
                'recorded_by' => $admin?->id,
            ]);

            $paid = round((float) $invoice->amount_paid + $amount, 2);
            // A rounding penny short should still count as settled.
            $settled = $paid + 0.009 >= (float) $invoice->amount;

            $invoice->update([
                'amount_paid' => $paid,
                'status' => $settled ? InvoiceStatus::Paid : InvoiceStatus::PartlyPaid,
                'paid_at' => $settled ? now() : null,
            ]);

            $this->reopenIfSettled($invoice->store);

            return $payment;
        });

        if ($invoice->fresh()?->status === InvoiceStatus::Paid) {
            $this->email($invoice->store, new SubscriptionPaymentReceived($invoice->fresh(), $payment), "receipt for {$invoice->number}");
        }

        return $payment;
    }

    /**
     * Mark what has fallen due, tell the shop once, then close the stores
     * whose grace period has run out. This is the "warn, then suspend" rule.
     *
     * @return array{overdue: int, suspended: int}
     */
    public function reviewOverdue(?CarbonInterface $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?? now())->startOfDay();
        $overdue = 0;

        $justDue = StoreInvoice::query()
            ->with('store')
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartlyPaid])
            ->whereDate('due_on', '<', $asOf)
            ->get();

        foreach ($justDue as $invoice) {
            $invoice->update(['status' => InvoiceStatus::Overdue]);
            $overdue++;

            // Said once, on the day it slips, rather than every night.
            if ($invoice->overdue_notified_at === null) {
                $invoice->update(['overdue_notified_at' => now()]);
                $this->email($invoice->store, new SubscriptionOverdue($invoice->fresh()), "overdue notice for {$invoice->number}");
            }
        }

        $suspended = 0;

        $candidates = StoreInvoice::query()
            ->with('store')
            ->overdue()
            ->get()
            ->filter(fn (StoreInvoice $invoice): bool => $invoice->store !== null
                && $invoice->store->isActive()
                && $invoice->store->auto_suspend
                && $asOf->greaterThan($invoice->suspendOn()));

        foreach ($candidates as $invoice) {
            $invoice->store->update([
                'status' => StoreStatus::Suspended,
                'suspended_at' => now(),
                'suspension_reason' => "Invoice {$invoice->number} for {$invoice->periodLabel()} is unpaid.",
            ]);

            $suspended++;
        }

        return ['overdue' => $overdue, 'suspended' => $suspended];
    }

    /**
     * A store closed for non-payment reopens once nothing is owed.
     */
    protected function reopenIfSettled(?Store $store): void
    {
        if ($store === null || ! $store->isSuspended()) {
            return;
        }

        $stillOwed = StoreInvoice::where('store_id', $store->id)->unpaid()->exists();

        if ($stillOwed) {
            return;
        }

        $store->update([
            'status' => StoreStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }

    /**
     * Write to the shop's owner. A mail server that is down must never stop
     * an invoice being raised or a payment being recorded.
     */
    public function email(?Store $store, Mailable $mailable, string $describedAs): void
    {
        $recipient = $store?->owner_email ?: $store?->email;

        if (blank($recipient)) {
            return;
        }

        try {
            Mail::to($recipient)->send($mailable);
        } catch (\Throwable $exception) {
            Log::warning("Could not email {$describedAs}: ".$exception->getMessage());
        }
    }

    protected function nextNumber(CarbonInterface $periodStart): string
    {
        $period = Carbon::parse($periodStart)->format('ym');

        $last = StoreInvoice::query()
            ->where('number', 'like', "INV-{$period}-%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last !== null ? ((int) substr((string) $last, -4)) + 1 : 1;

        return sprintf('INV-%s-%04d', $period, $sequence);
    }
}
