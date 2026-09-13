<x-mail::message>
# {{ $invoice->currency_code }} {{ number_format((float) $invoice->amount, 2) }} due on {{ $invoice->due_on->format('d M Y') }}

Hello {{ $store->owner_name ?? $store->name }},

This is the invoice for **{{ $store->name }}** covering {{ $invoice->periodLabel() }}.
@if ($invoice->is_prorated)
It covers only the part of the period you have used, so it is smaller than a full one.
@endif

<x-mail::table>
| | |
|:---|---:|
| Invoice | {{ $invoice->number }} |
| Period | {{ $invoice->periodLabel() }} |
| Due | {{ $invoice->due_on->format('d M Y') }} |
| **Amount** | **{{ $invoice->currency_code }} {{ number_format((float) $invoice->amount, 2) }}** |
</x-mail::table>

<x-mail::button :url="$payUrl">
Pay now
</x-mail::button>

Nothing is interrupted before the due date. If you have already paid, please ignore this.

Thanks,<br>
{{ config('tenancy.platform_name') }}
</x-mail::message>
