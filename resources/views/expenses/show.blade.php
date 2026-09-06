<x-layouts.app :title="$expense->reference_no">
    <x-page-header :title="$expense->reference_no"
        :description="$expense->description"
        :back="route('expenses.index')">
        <x-slot:actions>
            <x-button :href="route('expenses.edit', $expense)" variant="secondary">Edit</x-button>
            @can('delete-records')
                <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                    onsubmit="return confirm('Delete {{ $expense->reference_no }}?')">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="danger">Delete</x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Expense details">
                <dl class="divide-y divide-slate-100 text-sm">
                    @php
                        $symbol = config('inventory.currency_symbol');
                        $rows = [
                            'Date' => $expense->expense_date->format('d M Y'),
                            'Category' => $expense->category?->name ?? '—',
                            'Paid to' => $expense->paid_to,
                            'Bill / invoice no.' => $expense->invoice_number ?? '—',
                            'Amount excl. VAT' => $symbol.number_format((float) $expense->amount, 2),
                            'VAT @ '.rtrim(rtrim((string) $expense->vat_rate, '0'), '.').'%' => $symbol.number_format((float) $expense->vat_amount, 2),
                            'Total' => $symbol.number_format((float) $expense->total, 2),
                            'Paid by' => $expense->payment_method->label(),
                            'Status' => $expense->is_paid ? 'Paid on '.$expense->paid_on?->format('d M Y') : 'Still owed',
                            'Recorded by' => $expense->creator?->name ?? '—',
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

            @if ($expense->notes)
                <x-card title="Notes" class="mt-6">
                    <p class="px-5 py-4 text-sm leading-relaxed text-slate-600">{{ $expense->notes }}</p>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($expense->stockEntry)
                <x-card title="Linked goods receipt">
                    <div class="px-5 py-4 text-sm">
                        <a href="{{ route('stock-entries.show', $expense->stockEntry) }}"
                            class="font-mono font-medium text-brand-700 hover:underline">{{ $expense->stockEntry->reference_no }}</a>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $expense->stockEntry->supplier?->name }} ·
                            @money($expense->stockEntry->grand_total) on {{ $expense->stockEntry->entry_date->format('d M Y') }}
                        </p>
                    </div>
                </x-card>
            @endif

            <x-card title="Receipt">
                @if ($expense->attachment_path)
                    @if (str_ends_with(strtolower($expense->attachment_path), '.pdf'))
                        <div class="px-5 py-4">
                            <x-button :href="Storage::url($expense->attachment_path)" target="_blank" variant="secondary" size="sm">
                                Open the PDF receipt
                            </x-button>
                        </div>
                    @else
                        <a href="{{ Storage::url($expense->attachment_path) }}" target="_blank">
                            <img src="{{ Storage::url($expense->attachment_path) }}" alt="Receipt"
                                class="max-h-96 w-full object-contain p-3">
                        </a>
                    @endif
                @else
                    <x-empty-state icon="◧" title="No receipt attached"
                        description="Attach a photo of the bill so it can be found later." />
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
