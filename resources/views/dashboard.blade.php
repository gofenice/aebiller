<x-layouts.app title="Dashboard">
    <x-page-header :title="'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')).', '.auth()->user()->name"
        description="Here is where the inventory stands right now.">
        <x-slot:actions>
            <x-button :href="route('stock-entries.create')" variant="secondary">↓ Receive stock</x-button>
            <x-button :href="route('stock-adjustments.create')" variant="secondary">⇅ Adjust stock</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Stock value at cost"
            :value="config('inventory.currency_symbol').number_format($stats['stockValue'], 2)"
            :sub="'Retail '.config('inventory.currency_symbol').number_format($stats['retailValue'], 2)"
            icon="¤" :href="route('reports.valuation')" />
        <x-stat-card label="Products" :value="number_format($stats['products'])"
            :sub="$stats['packaged'].' packet · '.$stats['loose'].' loose'" icon="▤" :href="route('products.index')" />
        <x-stat-card label="Needs reordering" :value="number_format($stats['lowStock'] + $stats['outOfStock'])"
            :sub="$stats['outOfStock'].' completely out of stock'" tone="amber" icon="⚠"
            :href="route('reports.low-stock')" />
        <x-stat-card label="Expiring soon" :value="number_format($stats['expiringSoon'])"
            :sub="$stats['expired'] > 0 ? $stats['expired'].' already expired' : 'Within '.$expiryDays.' days'"
            :tone="$stats['expired'] > 0 ? 'red' : 'blue'" icon="⏱" :href="route('reports.expiry')" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Running low" description="At or below the reorder level.">
                <x-slot:actions>
                    <x-button :href="route('reports.low-stock')" variant="ghost" size="sm">Full list</x-button>
                </x-slot:actions>

                @if ($lowStockProducts->isEmpty() && $outOfStockProducts->isEmpty())
                    <x-empty-state icon="✓" title="Everything is well stocked"
                        description="No active product has dropped to its reorder level." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($outOfStockProducts->concat($lowStockProducts)->take(8) as $product)
                            <li class="flex items-center justify-between gap-4 px-5 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('products.show', $product) }}" class="block truncate text-sm font-medium text-slate-900 hover:text-brand-700">
                                        {{ $product->display_name }}
                                    </a>
                                    <p class="text-xs text-slate-500">{{ $product->category?->name }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="text-sm font-semibold {{ $product->isOutOfStock() ? 'text-red-600' : 'text-amber-600' }}">
                                        @qty($product->current_stock) {{ $product->unit?->code }}
                                    </span>
                                    <x-stock-badge :product="$product" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Latest stock movements">
                <x-slot:actions>
                    <x-button :href="route('stock-movements.index')" variant="ghost" size="sm">Full ledger</x-button>
                </x-slot:actions>

                @if ($recentMovements->isEmpty())
                    <x-empty-state icon="≡" title="No stock has moved yet"
                        description="Add products and receive your first delivery to get going.">
                        <x-slot:actions>
                            <x-button :href="route('products.create')">+ New product</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head">Product</th>
                                    <th class="table-head">Type</th>
                                    <th class="table-head text-right">Change</th>
                                    <th class="table-head text-right">Balance</th>
                                    <th class="table-head">When</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($recentMovements as $movement)
                                    <tr>
                                        <td class="table-cell">
                                            <a href="{{ route('products.show', $movement->product) }}" class="font-medium text-slate-900 hover:text-brand-700">
                                                {{ $movement->product->display_name }}
                                            </a>
                                        </td>
                                        <td class="table-cell text-slate-600">{{ $movement->type->label() }}</td>
                                        <td class="table-cell text-right font-semibold {{ $movement->isInward() ? 'text-emerald-600' : 'text-red-600' }}">
                                            {{ $movement->isInward() ? '+' : '−' }}@qty($movement->quantity)
                                        </td>
                                        <td class="table-cell text-right text-slate-700">@qty($movement->balance_after)</td>
                                        <td class="table-cell text-slate-500">{{ $movement->moved_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Expiring soon" :description="'Next '.$expiryDays.' days'">
                @if ($expiringBatches->isEmpty())
                    <x-empty-state icon="✓" title="Nothing going off"
                        description="No tracked batch expires in this window." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($expiringBatches as $batch)
                            @php
                                $daysLeft = (int) $batch->daysToExpiry();
                            @endphp
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('products.show', $batch->product) }}" class="block truncate text-sm font-medium text-slate-900 hover:text-brand-700">
                                        {{ $batch->product->display_name }}
                                    </a>
                                    <p class="text-xs text-slate-500">
                                        @qty($batch->quantity) {{ $batch->product->unit?->code }} · {{ $batch->expires_on->format('d M Y') }}
                                    </p>
                                </div>
                                <x-badge :color="$daysLeft <= 7 ? 'red' : 'amber'">{{ $daysLeft }}d</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Stock value by category">
                @if ($categoryBreakdown->isEmpty())
                    <x-empty-state icon="⌗" title="No stock yet" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($categoryBreakdown as $row)
                            <li class="px-5 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="truncate text-sm text-slate-700">{{ $row->name }}</span>
                                    <span class="shrink-0 text-sm font-semibold text-slate-900">@money($row->value)</span>
                                </div>
                                @if ($stats['stockValue'] > 0)
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-brand-500"
                                            style="width: {{ min(100, round(($row->value / $stats['stockValue']) * 100, 1)) }}%"></div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Recent deliveries">
                @if ($recentEntries->isEmpty())
                    <x-empty-state icon="↓" title="No deliveries recorded" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($recentEntries as $entry)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('stock-entries.show', $entry) }}" class="block font-mono text-sm font-medium text-brand-700 hover:underline">
                                        {{ $entry->reference_no }}
                                    </a>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ $entry->supplier?->name ?? $entry->type->label() }} · {{ $entry->items->count() }} line(s)
                                    </p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-slate-900">@money($entry->grand_total)</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
