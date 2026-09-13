<x-mail::message>
# Payment received — thank you

Hello {{ $store->owner_name ?? $store->name }},

We have received your payment for **{{ $store->name }}**. Nothing further is owed on this invoice.

<x-mail::table>
| | |
|:---|---:|
| Invoice | {{ $invoice->number }} |
| Period | {{ $invoice->periodLabel() }} |
| Paid on | {{ $payment->received_on->format('d M Y') }} |
| How | {{ $payment->method->label() }}{{ $payment->reference ? ' · '.$payment->reference : '' }} |
| **Amount** | **{{ $payment->currency_code }} {{ number_format((float) $payment->amount, 2) }}** |
</x-mail::table>

@if ($store->next_invoice_on)
Your next payment of {{ $store->feeLabel() }} is due on {{ $store->next_invoice_on->format('d M Y') }}.
@else
There are no further payments due.
@endif

Thanks,<br>
{{ config('tenancy.platform_name') }}
</x-mail::message>
