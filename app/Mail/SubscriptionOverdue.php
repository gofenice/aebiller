<?php

namespace App\Mail;

use App\Models\StoreInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once, the day an invoice passes its due date, saying plainly when the
 * shop will be closed if it stays unpaid.
 */
class SubscriptionOverdue extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public StoreInvoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment overdue — {$this->invoice->number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscription.overdue',
            with: [
                'invoice' => $this->invoice,
                'store' => $this->invoice->store,
                'payUrl' => $this->invoice->store?->url().'/subscription',
                'closesOn' => $this->invoice->store?->auto_suspend ? $this->invoice->suspendOn() : null,
            ],
        );
    }
}
