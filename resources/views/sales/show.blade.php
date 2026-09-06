<x-layouts.app :title="$sale->invoice_no">
    <div class="no-print">
        <x-page-header :title="$sale->invoice_no"
            :description="$sale->sold_at->format('d M Y, g:i a').' · '.$sale->payment_method->label()"
            :back="route('sales.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" onclick="window.print()">🖨 Print bill</x-button>
                <x-button :href="route('billing.create')">+ New sale</x-button>
            </x-slot:actions>
        </x-page-header>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <x-bill.receipt :sale="$sale" :qr-svg="$qrSvg" :public-url="$publicUrl" />
        </div>

        <div class="no-print space-y-6 lg:col-span-3">
            <x-card title="Online bill" description="The address behind the QR code on the receipt.">
                <div class="space-y-3 p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" readonly value="{{ $publicUrl }}" class="form-input flex-1 font-mono text-xs"
                            onclick="this.select()">
                        <x-button :href="$publicUrl" target="_blank" variant="secondary" size="sm">Open</x-button>
                    </div>
                    <p class="text-xs leading-relaxed text-slate-500">
                        Anyone holding the receipt can open this page — no sign-in needed. For a customer to scan it
                        from their phone, the app has to be reachable on the shop network: run
                        <code class="rounded bg-slate-100 px-1 py-0.5 font-mono">php artisan serve --host=0.0.0.0</code>
                        and browse to the machine's LAN address, so the QR is printed with that address instead of localhost.
                    </p>
                </div>
            </x-card>

            <x-card title="Bill summary">
                <dl class="divide-y divide-slate-100 text-sm">
                    @php
                        $rows = [
                            'Status' => $sale->status->label(),
                            'Items' => $sale->items->count().' line(s), '.rtrim(rtrim(number_format((float) $sale->items->sum('quantity'), 3), '0'), '.').' units',
                            'Total excl. VAT' => config('inventory.currency_symbol').number_format((float) $sale->subtotal_excl_vat, 2),
                            'VAT' => config('inventory.currency_symbol').number_format((float) $sale->vat_total, 2),
                            'Grand total' => config('inventory.currency_symbol').number_format((float) $sale->grand_total, 2),
                            'Payment' => $sale->payment_method->label(),
                            'Cash received' => config('inventory.currency_symbol').number_format((float) $sale->amount_paid, 2),
                            'Change given' => config('inventory.currency_symbol').number_format((float) $sale->change_due, 2),
                            'Cost of goods' => config('inventory.currency_symbol').number_format((float) $sale->cost_total, 2),
                            'Gross profit' => config('inventory.currency_symbol').number_format($sale->profit(), 2),
                            'Cashier' => $sale->cashier?->name ?? '—',
                        ];
                    @endphp
                    @foreach ($rows as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-2.5">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            @if ($sale->isVoided())
                <x-card title="Void record">
                    <div class="space-y-1 px-5 py-4 text-sm text-slate-600">
                        <p>Voided by {{ $sale->voider?->name ?? '—' }} on {{ $sale->voided_at?->format('d M Y, g:i a') }}.</p>
                        @if ($sale->void_reason)
                            <p class="text-slate-500">Reason: {{ $sale->void_reason }}</p>
                        @endif
                        <p class="text-xs text-slate-400">The sold quantities were returned to stock.</p>
                    </div>
                </x-card>
            @elsecan('delete-records')
                <x-card title="Void this bill">
                    <form method="POST" action="{{ route('sales.destroy', $sale) }}" class="space-y-3 p-5"
                        onsubmit="return confirm('Void {{ $sale->invoice_no }}? The items go back into stock.')">
                        @csrf
                        @method('DELETE')
                        <x-field label="Reason" name="void_reason">
                            <x-input name="void_reason" id="void_reason" placeholder="e.g. Wrong item scanned" />
                        </x-field>
                        <x-button type="submit" variant="danger" size="sm">Void bill &amp; return stock</x-button>
                        <p class="form-hint">Super admin only. The bill stays on record, marked as voided.</p>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
