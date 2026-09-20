<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreditPaymentRequest;
use App\Http\Requests\StoreOpeningDueRequest;
use App\Models\Customer;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * The balance a customer carried over from the shop's own book, and the money
 * taken against it.
 */
class OpeningDueController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    /**
     * Set — or correct — what this customer owed from before.
     */
    public function store(StoreOpeningDueRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $this->billing->setOpeningDue($customer, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $customer->refresh();

        $message = (float) $customer->opening_due > 0
            ? sprintf('%s owed %s from before. It is on their account now.', $customer->name, number_format((float) $customer->opening_due, 2))
            : "The carried-over balance was cleared from {$customer->name}'s account.";

        return back()->with('status', $message);
    }

    /**
     * Take money against that carried-over balance.
     */
    public function pay(StoreCreditPaymentRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $payment = $this->billing->settleOpeningDue($customer, $request->validated(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $customer->refresh();

        $remaining = (float) $customer->opening_due_outstanding;

        return back()->with('status', $remaining > 0
            ? sprintf(
                'Took %s from %s — %s still owed from before.',
                number_format((float) $payment->amount, 2),
                $customer->name,
                number_format($remaining, 2),
            )
            : "{$customer->name} has cleared what they owed from before.");
    }
}
