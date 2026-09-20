<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreditPaymentRequest;
use App\Models\Sale;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class CreditPaymentController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    /**
     * Take money against a credit bill, in full or in part.
     */
    public function store(StoreCreditPaymentRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $payment = $this->billing->settleCredit($sale, $request->validated(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $sale->refresh();

        $message = $sale->isSettled()
            ? "Bill {$sale->invoice_no} is now paid in full."
            : sprintf(
                'Took %s against bill %s — %s still owed.',
                number_format((float) $payment->amount, 2),
                $sale->invoice_no,
                number_format((float) $sale->amount_outstanding, 2),
            );

        return back()->with('status', $message);
    }
}
