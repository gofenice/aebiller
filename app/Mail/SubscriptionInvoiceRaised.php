<?php

namespace App\Mail;

use App\Models\StoreInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a subscription invoice is raised — days before it falls due, so
 * the shop has time to pay.
 */
class SubscriptionInvoiceRaised extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public StoreInvoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '%s %s due %s — %s',
                $this->invoice->currency_code,
                number_format((float) $this->invoice->amount, 2),
                $this->invoice->due_on->format('d M Y'),
                config('tenancy.platform_name'),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscription.invoice-raised',
            with: [
                'invoice' => $this->invoice,
                'store' => $this->invoice->store,
                'payUrl' => $this->invoice->store?->url().'/subscription',
            ],
        );
    }
}
