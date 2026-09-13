<x-layouts.platform :title="$invoice->number">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('platform.billing.index') }}" class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-brand-700">&larr; Billing</a>
            <h1 class="flex items-center gap-2 font-mono text-xl font-semibold tracking-tight text-slate-900">
                {{ $invoice->number }}
                <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                <a href="{{ route('platform.stores.show', $invoice->store) }}" class="hover:text-brand-700">{{ $invoice->store?->name }}</a>
                · {{ $invoice->periodLabel() }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('platform.billing.notify', $invoice) }}">
                @csrf
                <x-button type="submit" variant="secondary" size="sm">✉ Email this invoice</x-button>
            </form>

            @unless ($invoice->isSettled())
                <form method="POST" action="{{ route('platform.billing.void', $invoice) }}"
                    onsubmit="return confirm('Cancel invoice {{ $invoice->number }}?')">
                    @csrf
                    <x-button type="submit" variant="ghost" size="sm" class="text-red-600 hover:bg-red-50">Cancel invoice</x-button>
                </form>
            @endunless
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Invoice">
                <dl class="divide-y divide-slate-100 text-sm">
                    @php
                        $rows = [
                            'Store' => $invoice->store?->name.' ('.$invoice->store?->host().')',
                            'Plan' => $invoice->plan?->name ?? 'No plan',
                            'Period' => $invoice->period_end
                                ? $invoice->period_start->format('d M Y').' – '.$invoice->period_end->format('d M Y').($invoice->is_prorated ? ' (part period)' : '')
                                : 'Lifetime',
                            'Issued' => $invoice->issued_on->format('d M Y'),
                            'Due' => $invoice->due_on->format('d M Y'),
                            'Amount' => $invoice->currency_code.' '.number_format((float) $invoice->amount, 2),
                            'Paid so far' => $invoice->currency_code.' '.number_format((float) $invoice->amount_paid, 2),
                            'Outstanding' => $invoice->currency_code.' '.number_format($invoice->outstanding(), 2),
                        ];
                    @endphp
                    @foreach ($rows as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-2.5">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($invoice->razorpay_short_url)
                    <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
                        Razorpay link:
                        <a href="{{ $invoice->razorpay_short_url }}" target="_blank" class="font-mono text-brand-700 hover:underline">{{ $invoice->razorpay_short_url }}</a>
                        @if ($invoice->razorpay_payment_id)
                            · paid as <span class="font-mono">{{ $invoice->razorpay_payment_id }}</span>
                        @endif
                    </p>
                @endif

                @if ($invoice->isOverdue() && $invoice->store?->auto_suspend)
                    <p class="border-t border-red-100 bg-red-50 px-5 py-3 text-sm text-red-800">
                        Unpaid since {{ $invoice->due_on->format('d M Y') }} — the shop closes on {{ $invoice->suspendOn()->format('d M Y') }}.
                    </p>
                @endif
            </x-card>

            <x-card title="Payments received">
                @if ($invoice->payments->isEmpty())
                    <x-empty-state icon="¤" title="Nothing received yet" />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head">Received</th>
                                    <th class="table-head">Method</th>
                                    <th class="table-head">Reference</th>
                                    <th class="table-head">Recorded by</th>
                                    <th class="table-head text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->payments as $payment)
                                    <tr>
                                        <td class="table-cell text-slate-600">{{ $payment->received_on->format('d M Y') }}</td>
                                        <td class="table-cell text-slate-600">{{ $payment->method->label() }}</td>
                                        <td class="table-cell font-mono text-xs text-slate-500">{{ $payment->reference ?? '—' }}</td>
                                        <td class="table-cell text-slate-500">{{ $payment->recorder?->name ?? '—' }}</td>
                                        <td class="table-cell text-right font-semibold text-slate-900">
                                            {{ $payment->currency_code }} {{ number_format((float) $payment->amount, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div>
            @unless ($invoice->isSettled())
                <x-card title="Record a payment" description="Paying everything owed reopens a closed store.">
                    <form method="POST" action="{{ route('platform.billing.pay', $invoice) }}" class="space-y-4 p-5">
                        @csrf

                        <x-field label="Amount ({{ $invoice->currency_code }})" name="amount" required>
                            <x-input type="number" step="0.01" min="0.01" name="amount" id="amount"
                                value="{{ old('amount', number_format($invoice->outstanding(), 2, '.', '')) }}" required class="text-right" />
                        </x-field>

                        <x-field label="How it was paid" name="method" required>
                            <x-select name="method" id="method">
                                @foreach ($methods as $method)
                                    <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </x-select>
                        </x-field>

                        <x-field label="Reference" name="reference" hint="Transfer number, cheque number…">
                            <x-input name="reference" id="reference" value="{{ old('reference') }}" />
                        </x-field>

                        <x-field label="Received on" name="received_on" required>
                            <x-input type="date" name="received_on" id="received_on"
                                value="{{ old('received_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required />
                        </x-field>

                        <x-field label="Notes" name="notes">
                            <x-textarea name="notes" id="notes" rows="2">{{ old('notes') }}</x-textarea>
                        </x-field>

                        <x-button type="submit" class="w-full">Record payment</x-button>
                    </form>
                </x-card>
            @else
                <x-card title="Settled">
                    <p class="px-5 py-4 text-sm text-slate-600">
                        {{ $invoice->status === \App\Enums\InvoiceStatus::Paid
                            ? 'Paid in full'.($invoice->paid_at ? ' on '.$invoice->paid_at->format('d M Y') : '').'.'
                            : 'This invoice was cancelled.' }}
                    </p>
                </x-card>
            @endunless
        </div>
    </div>
</x-layouts.platform>
