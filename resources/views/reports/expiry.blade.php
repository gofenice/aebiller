<x-layouts.app title="Expiry report">
    <x-page-header title="Expiry watch"
        :description="'Batches already expired or going off within '.$days.' days.'" />

    @if ($expiredCount > 0)
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <strong>{{ $expiredCount }}</strong> batch(es) have already expired and are still counted in stock.
            Write them off with a stock adjustment (reason: expired goods).
        </div>
    @endif

    <x-card>
        <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
            <label class="text-sm text-slate-600">Show batches expiring within</label>
            <x-select name="days" class="w-32" x-on:change="$el.form.submit()">
                @foreach ([7, 15, 30, 60, 90, 180] as $option)
                    <option value="{{ $option }}" @selected($days === $option)>{{ $option }} days</option>
                @endforeach
            </x-select>
            <x-button type="submit" variant="secondary">Apply</x-button>
        </form>

        @if ($batches->isEmpty())
            <x-empty-state icon="✓" title="Nothing going off"
                description="No batches expire inside the selected window." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Product</th>
                            <th class="table-head">Batch</th>
                            <th class="table-head">Supplier</th>
                            <th class="table-head">Expires</th>
                            <th class="table-head text-right">Days left</th>
                            <th class="table-head text-right">Quantity</th>
                            <th class="table-head text-right">Value at cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($batches as $batch)
                            @php
                                $days_left = (int) $batch->daysToExpiry();
                            @endphp
                            <tr class="hover:bg-slate-50/70 {{ $days_left < 0 ? 'bg-red-50/40' : '' }}">
                                <td class="table-cell">
                                    <a href="{{ route('products.show', $batch->product) }}" class="font-medium text-slate-900 hover:text-brand-700">
                                        {{ $batch->product->display_name }}
                                    </a>
                                    <span class="block text-xs text-slate-400">{{ $batch->product->category?->name }}</span>
                                </td>
                                <td class="table-cell font-mono text-xs text-slate-600">{{ $batch->batch_number ?? '—' }}</td>
                                <td class="table-cell text-slate-600">{{ $batch->supplier?->name ?? '—' }}</td>
                                <td class="table-cell text-slate-700">{{ $batch->expires_on->format('d M Y') }}</td>
                                <td class="table-cell text-right">
                                    @if ($days_left < 0)
                                        <x-badge color="red">Expired {{ abs($days_left) }}d ago</x-badge>
                                    @elseif ($days_left <= 7)
                                        <x-badge color="red">{{ $days_left }}d</x-badge>
                                    @else
                                        <x-badge color="amber">{{ $days_left }}d</x-badge>
                                    @endif
                                </td>
                                <td class="table-cell text-right font-medium">
                                    @qty($batch->quantity) <span class="text-xs font-normal text-slate-400">{{ $batch->product->unit?->code }}</span>
                                </td>
                                <td class="table-cell text-right text-slate-700">@money((float) $batch->quantity * (float) $batch->cost_price)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $batches->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
