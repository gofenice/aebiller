<x-mail::message>
# Payment overdue

Hello {{ $store->owner_name ?? $store->name }},

Invoice {{ $invoice->number }} for **{{ $store->name }}** was due on {{ $invoice->due_on->format('d M Y') }} and is still unpaid.

<x-mail::table>
| | |
|:---|---:|
| Period | {{ $invoice->periodLabel() }} |
| Due | {{ $invoice->due_on->format('d M Y') }} |
| **Still owed** | **{{ $invoice->currency_code }} {{ number_format($invoice->outstanding(), 2) }}** |
</x-mail::table>

@if ($closesOn)
If it is not paid by **{{ $closesOn->format('d M Y') }}**, the shop will be closed — tills, stock and reports — until it is. Paying reopens it at once.
@endif

<x-mail::button :url="$payUrl" color="error">
Pay now
</x-mail::button>

If you have already paid, or something is wrong with this invoice, please reply and we will sort it out.

Thanks,<br>
{{ config('tenancy.platform_name') }}
</x-mail::message>
