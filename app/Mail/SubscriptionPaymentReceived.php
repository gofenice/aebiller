<?php

namespace App\Mail;

use App\Models\StoreInvoice;
use App\Models\StorePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The receipt for a subscription payment, however it was paid.
 */
class SubscriptionPaymentReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public StoreInvoice $invoice, public StorePayment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment received — {$this->invoice->number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscription.payment-received',
            with: [
                'invoice' => $this->invoice,
                'payment' => $this->payment,
                'store' => $this->invoice->store,
            ],
        );
    }
}
