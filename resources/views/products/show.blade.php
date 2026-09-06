<x-layouts.app :title="$product->name">
    <x-page-header :title="$product->display_name"
        :description="$product->sku.($product->barcode ? ' · '.$product->barcode : '')"
        :back="route('products.index')">
        <x-slot:actions>
            <x-button :href="route('stock-entries.create')" variant="secondary">↓ Receive stock</x-button>
            <x-button :href="route('stock-adjustments.create')" variant="secondary">⇅ Adjust</x-button>
            <x-button :href="route('products.edit', $product)">Edit product</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card label="On hand" :value="rtrim(rtrim(number_format((float) $product->current_stock, 3), '0'), '.').' '.$product->unit?->code"
            :tone="$product->isOutOfStock() ? 'red' : ($product->isLowOnStock() ? 'amber' : 'green')" icon="▦" />
        <x-stat-card label="Stock value at cost" :value="config('inventory.currency_symbol').number_format($product->stock_value, 2)" icon="¤" />
        <x-stat-card label="Customer pays" :value="config('inventory.currency_symbol').number_format($product->price_including_tax, 2)"
            :sub="config('inventory.currency_symbol').number_format($product->price_excluding_tax, 2).' + '.config('inventory.currency_symbol').number_format($product->tax_amount, 2).' VAT'"
            icon="◈" />
        <x-stat-card label="Margin" :value="$product->margin_percent !== null ? $product->margin_percent.'%' : '—'"
            :sub="'on net, cost '.config('inventory.currency_symbol').number_format((float) $product->cost_price, 2)"
            :tone="($product->margin_percent ?? 0) < 0 ? 'red' : 'green'" icon="↗" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if ($product->track_batches || $product->track_expiry)
                <x-card title="Batches in stock" description="Issued oldest expiry first.">
                    @if ($batches->isEmpty())
                        <x-empty-state icon="⏱" title="No open batches"
                            description="Batches appear here once stock is received against this product." />
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="table-head">Batch</th>
                                        <th class="table-head">Received</th>
                                        <th class="table-head">Expires</th>
                                        <th class="table-head text-right">Quantity</th>
                                        <th class="table-head text-right">Cost</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($batches as $batch)
                                        @php
                                            $days = $batch->daysToExpiry();
                                        @endphp
                                        <tr>
                                            <td class="table-cell font-mono text-slate-700">{{ $batch->batch_number ?? '—' }}</td>
                                            <td class="table-cell text-slate-500">{{ $batch->received_on?->format('d M Y') ?? '—' }}</td>
                                            <td class="table-cell">
                                                @if ($batch->expires_on)
                                                    <span class="text-slate-700">{{ $batch->expires_on->format('d M Y') }}</span>
                                                    @if ($days !== null && $days < 0)
                                                        <x-badge color="red" class="ml-1">Expired</x-badge>
                                                    @elseif ($days !== null && $days <= config('inventory.expiry_alert_days'))
                                                        <x-badge color="amber" class="ml-1">{{ (int) $days }}d left</x-badge>
                                                    @endif
                                                @else
                                                    <span class="text-slate-400">—</span>
                                                @endif
                                            </td>
                                            <td class="table-cell text-right font-medium">@qty($batch->quantity) {{ $product->unit?->code }}</td>
                                            <td class="table-cell text-right text-slate-600">@money($batch->cost_price)</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            @endif

            <x-card title="Stock ledger" description="Most recent 25 movements.">
                <x-slot:actions>
                    <x-button :href="route('stock-movements.index', ['product' => $product->id])" variant="ghost" size="sm">View all</x-button>
                </x-slot:actions>

                @if ($movements->isEmpty())
                    <x-empty-state icon="≡" title="No stock movements yet"
                        description="Receive stock against this product and every in and out will be listed here." />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head">Date</th>
                                    <th class="table-head">Type</th>
                                    <th class="table-head">Reference</th>
                                    <th class="table-head text-right">Change</th>
                                    <th class="table-head text-right">Balance</th>
                                    <th class="table-head">By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($movements as $movement)
                                    <tr>
                                        <td class="table-cell text-slate-500">{{ $movement->moved_at->format('d M Y') }}</td>
                                        <td class="table-cell text-slate-700">{{ $movement->type->label() }}</td>
                                        <td class="table-cell font-mono text-xs text-slate-500">{{ $movement->reference ?? '—' }}</td>
                                        <td class="table-cell text-right font-semibold {{ $movement->isInward() ? 'text-emerald-600' : 'text-red-600' }}">
                                            {{ $movement->isInward() ? '+' : '−' }}@qty($movement->quantity)
                                        </td>
                                        <td class="table-cell text-right text-slate-700">@qty($movement->balance_after)</td>
                                        <td class="table-cell text-slate-500">{{ $movement->user?->name ?? 'System' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($product->image_path)
                <div class="card overflow-hidden">
                    <img src="{{ Storage::url($product->image_path) }}" alt="{{ $product->name }}" class="h-48 w-full object-cover">
                </div>
            @endif

            <x-card title="Details">
                <dl class="divide-y divide-slate-100 text-sm">
                    @php
                        $rows = [
                            'Type' => $product->type->label(),
                            'Category' => $product->category?->name ?? '—',
                            'Brand' => $product->brand?->name ?? '—',
                            'Selling unit' => $product->unit?->name.' ('.$product->unit?->code.')',
                            'Pack size' => $product->pack_label ?? '—',
                            'Units per case' => $product->units_per_case ?? '—',
                            'Price excl. VAT' => config('inventory.currency_symbol').number_format($product->price_excluding_tax, 2),
                            'VAT @ '.rtrim(rtrim((string) $product->tax_rate, '0'), '.').'%' => config('inventory.currency_symbol').number_format($product->tax_amount, 2),
                            'Price incl. VAT' => config('inventory.currency_symbol').number_format($product->price_including_tax, 2),
                            'HS code' => $product->hs_code ?? '—',
                            'Reorder level' => rtrim(rtrim(number_format((float) $product->reorder_level, 3), '0'), '.').' '.$product->unit?->code,
                            'Maximum stock' => $product->max_stock_level ? rtrim(rtrim(number_format((float) $product->max_stock_level, 3), '0'), '.').' '.$product->unit?->code : '—',
                            'Storage' => $product->storage_type->label(),
                            'Rack location' => $product->rack_location ?? '—',
                            'Default supplier' => $product->supplier?->name ?? '—',
                            'Batch tracking' => $product->track_batches ? 'On' : 'Off',
                            'Expiry tracking' => $product->track_expiry ? 'On' : 'Off',
                            'Shelf life' => $product->shelf_life_days ? $product->shelf_life_days.' days' : '—',
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

            @if ($product->description)
                <x-card title="Notes">
                    <p class="px-5 py-4 text-sm leading-relaxed text-slate-600">{{ $product->description }}</p>
                </x-card>
            @endif

            <x-card title="Record">
                <div class="space-y-1.5 px-5 py-4 text-xs text-slate-500">
                    <p>Created {{ $product->created_at->format('d M Y, g:i a') }}
                        @if ($product->creator) by {{ $product->creator->name }} @endif
                    </p>
                    <p>Last updated {{ $product->updated_at->diffForHumans() }}</p>
                </div>

                @can('delete-records')
                    <div class="border-t border-slate-200 px-5 py-4">
                        <form method="POST" action="{{ route('products.destroy', $product) }}"
                            onsubmit="return confirm('Remove {{ addslashes($product->name) }} from the catalogue?')">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger" size="sm" class="w-full">Delete product</x-button>
                        </form>
                        <p class="form-hint">Super admin only. The stock ledger is kept.</p>
                    </div>
                @endcan
            </x-card>
        </div>
    </div>
</x-layouts.app>
