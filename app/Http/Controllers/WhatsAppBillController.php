<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\WhatsAppGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sending the customer a copy of their bill on WhatsApp.
 */
class WhatsAppBillController extends Controller
{
    public function __construct(protected WhatsAppGateway $whatsapp) {}

    public function __invoke(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('run-till');

        if (! $this->whatsapp->enabled()) {
            return back()->with('error', 'WhatsApp is not set up yet, so the bill cannot be sent.');
        }

        $to = $this->whatsapp->recipientFor($sale);

        if ($to === null) {
            return back()->with('error', 'There is no mobile number on this bill to send it to.');
        }

        $message = $this->whatsapp->sendBill($sale, $request->user());

        if ($message === null || $message->hasFailed()) {
            return back()->with('error', 'WhatsApp would not accept the message: '.($message?->error ?: 'no reason given').'.');
        }

        return back()->with('status', "Bill {$sale->invoice_no} was sent to {$this->whatsapp->mask($to)} on WhatsApp.");
    }
}
