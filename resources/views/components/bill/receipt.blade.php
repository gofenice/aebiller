@props(['sale', 'qrSvg' => null, 'publicUrl' => null])

@php
    $symbol = config('inventory.currency_symbol');
@endphp

<div class="receipt mx-auto w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
    <div class="text-center">
        <p class="text-base font-bold tracking-tight text-slate-900">{{ config('app.name') }}</p>
        <p class="mt-0.5 text-[11px] text-slate-500">Simplified Tax Invoice · فاتورة ضريبية مبسطة</p>
        @if (config('inventory.store_vat_number'))
            <p class="mt-1 text-[11px] text-slate-500">VAT No. {{ config('inventory.store_vat_number') }}</p>
        @endif
    </div>

    @if ($sale->isVoided())
        <p class="mt-3 rounded border border-red-300 bg-red-50 px-2 py-1 text-center text-xs font-bold tracking-wide text-red-700 uppercase">
            Voided
        </p>
    @endif

    <dl class="mt-4 space-y-1 border-y border-dashed border-slate-300 py-3 text-[11px] text-slate-600">
        <div class="flex justify-between">
            <dt>Invoice</dt>
            <dd class="font-mono font-semibold text-slate-900">{{ $sale->invoice_no }}</dd>
        </div>
        <div class="flex justify-between">
            <dt>Date</dt>
            <dd>{{ $sale->sold_at->format('d M Y, g:i a') }}</dd>
        </div>
        <div class="flex justify-between">
            <dt>Cashier</dt>
            <dd>{{ $sale->cashier?->name ?? '—' }}</dd>
        </div>
        @if ($sale->customer_name)
            <div class="flex justify-between">
                <dt>Customer</dt>
                <dd>{{ $sale->customer_name }}</dd>
            </div>
        @endif
        @if ($sale->customer_vat_number)
            <div class="flex justify-between">
                <dt>Customer VAT</dt>
                <dd class="font-mono">{{ $sale->customer_vat_number }}</dd>
            </div>
        @endif
    </dl>

    <table class="mt-3 w-full text-[11px]">
        <thead>
            <tr class="border-b border-slate-200 text-left text-slate-500">
                <th class="pb-1 font-medium">Item</th>
                <th class="pb-1 text-right font-medium">Qty</th>
                <th class="pb-1 text-right font-medium">Price</th>
                <th class="pb-1 text-right font-medium">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($sale->items as $item)
                <tr>
                    <td class="py-1.5 pr-2 align-top text-slate-800">
                        {{ $item->name }}
                        @if ((float) $item->discount_percent > 0)
                            <span class="block text-[10px] text-slate-400">less {{ rtrim(rtrim((string) $item->discount_percent, '0'), '.') }}%</span>
                        @endif
                    </td>
                    <td class="py-1.5 text-right align-top whitespace-nowrap text-slate-600">
                        @qty($item->quantity) {{ $item->unit_code }}
                    </td>
                    <td class="py-1.5 text-right align-top text-slate-600">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="py-1.5 text-right align-top font-medium text-slate-900">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <dl class="mt-3 space-y-1 border-t border-dashed border-slate-300 pt-3 text-[11px]">
        @if ((float) $sale->line_discount_total > 0)
            <div class="flex justify-between text-slate-600">
                <dt>Item discounts</dt>
                <dd>− {{ number_format((float) $sale->line_discount_total, 2) }}</dd>
            </div>
        @endif
        @if ((float) $sale->bill_discount > 0)
            <div class="flex justify-between text-slate-600">
                <dt>Bill discount</dt>
                <dd>− {{ number_format((float) $sale->bill_discount, 2) }}</dd>
            </div>
        @endif
        <div class="flex justify-between text-slate-600">
            <dt>Total excluding VAT</dt>
            <dd>{{ number_format((float) $sale->subtotal_excl_vat, 2) }}</dd>
        </div>
        <div class="flex justify-between text-slate-600">
            <dt>VAT</dt>
            <dd>{{ number_format((float) $sale->vat_total, 2) }}</dd>
        </div>
        <div class="flex justify-between border-t border-slate-300 pt-2 text-sm font-bold text-slate-900">
            <dt>Total</dt>
            <dd>{{ $symbol }}{{ number_format((float) $sale->grand_total, 2) }}</dd>
        </div>
        <div class="flex justify-between pt-1 text-slate-600">
            <dt>Paid ({{ $sale->payment_method->shortLabel() }})</dt>
            <dd>{{ number_format((float) $sale->amount_paid, 2) }}</dd>
        </div>
        @if ((float) $sale->change_due > 0)
            <div class="flex justify-between text-slate-600">
                <dt>Change</dt>
                <dd>{{ number_format((float) $sale->change_due, 2) }}</dd>
            </div>
        @endif
    </dl>

    @if ($qrSvg)
        <div class="mt-4 flex flex-col items-center border-t border-dashed border-slate-300 pt-4">
            <div class="[&>svg]:size-32">{!! $qrSvg !!}</div>
            <p class="mt-2 text-center text-[10px] leading-relaxed text-slate-500">
                Scan to open this bill online
                @if ($publicUrl)
                    <span class="mt-0.5 block font-mono break-all text-slate-400">{{ $publicUrl }}</span>
                @endif
            </p>
        </div>
    @endif

    <p class="mt-4 text-center text-[11px] text-slate-500">Thank you for shopping with us · شكراً لتسوقكم معنا</p>
</div>
