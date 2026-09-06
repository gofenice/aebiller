<x-layouts.app title="Stock adjustment">
    <x-page-header title="Stock adjustment" :description="'Reference '.$nextReference"
        :back="route('stock-adjustments.index')" />

    <form method="POST" action="{{ route('stock-adjustments.store') }}"
        x-data="stockAdjustmentForm({
            lookupUrl: '{{ route('products.lookup') }}',
            reason: '{{ old('reason', $selectedReason->value) }}',
            rows: {{ Js::from($oldRows) }},
        })">
        @csrf

        <div class="space-y-6">
            <x-form-section title="Why is stock changing?" icon="⇅"
                description="Damage, spoilage, staff use or a physical count — each one is recorded against your name.">
                <div class="grid gap-4 sm:grid-cols-6">
                    <x-field class="sm:col-span-3" label="Reason" name="reason" required>
                        <x-select name="reason" id="reason" x-model="reason" required>
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field class="sm:col-span-3" label="Date" name="adjustment_date" required>
                        <x-input type="date" name="adjustment_date" id="adjustment_date"
                            value="{{ old('adjustment_date', now()->toDateString()) }}"
                            max="{{ now()->toDateString() }}" required />
                    </x-field>

                    <div class="sm:col-span-6" x-show="isCount" x-cloak>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                            In a physical count you enter the <strong>counted quantity</strong>. The system works out the
                            difference against the recorded stock and posts it in the right direction.
                        </div>
                    </div>
                </div>
            </x-form-section>

            <x-form-section title="Products" icon="▤"
                description="Search by name, SKU or barcode.">
                <div class="overflow-x-auto" x-on:click.outside="closePicker()">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-slate-200">
                                <th class="table-head w-8">#</th>
                                <th class="table-head min-w-64">Product</th>
                                <th class="table-head w-32" x-show="!isCount">Direction</th>
                                <th class="table-head w-32 text-right">
                                    <span x-text="isCount ? 'Counted qty' : 'Quantity'"></span>
                                </th>
                                <th class="table-head w-28 text-right">Unit cost</th>
                                <th class="table-head w-32 text-right">New balance</th>
                                <th class="table-head w-28 text-right">Value</th>
                                <th class="table-head w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in rows" :key="index">
                                <tr class="border-b border-slate-100 align-top">
                                    <td class="px-2 py-3 text-xs text-slate-400" x-text="index + 1"></td>

                                    <td class="relative px-2 py-2">
                                        <input type="hidden" x-bind:name="`items[${index}][product_id]`" x-bind:value="row.product_id">

                                        <input type="text" class="form-input" placeholder="Search product…"
                                            x-model="row.term"
                                            x-on:input="search(index, row.term); clearRow(index)"
                                            x-on:focus="search(index, row.term)"
                                            autocomplete="off">

                                        <template x-if="row.product">
                                            <p class="mt-1 text-xs text-slate-500">
                                                <span class="font-mono" x-text="row.product.sku"></span>
                                                · recorded stock <span class="font-medium" x-text="row.product.current_stock"></span>
                                                <span x-text="row.product.unit"></span>
                                            </p>
                                        </template>

                                        <template x-if="openRow === index && results.length">
                                        <div class="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                            <template x-for="result in results" :key="result.id">
                                                <button type="button" x-on:click="choose(index, result)"
                                                    class="flex w-full items-start justify-between gap-3 px-3 py-2 text-left hover:bg-brand-50">
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-sm font-medium text-slate-800" x-text="result.name"></span>
                                                        <span class="block font-mono text-xs text-slate-400" x-text="result.sku"></span>
                                                    </span>
                                                    <span class="shrink-0 text-xs text-slate-500">
                                                        on hand <span x-text="result.current_stock"></span> <span x-text="result.unit"></span>
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                        </template>

                                        <template x-if="row.product">
                                            <input type="text" class="form-input mt-2 py-1.5 text-xs" placeholder="Line note (optional)"
                                                x-bind:name="`items[${index}][notes]`" x-model="row.notes">
                                        </template>
                                    </td>

                                    <td class="px-2 py-2" x-show="!isCount">
                                        <select class="form-input" x-bind:name="`items[${index}][direction]`"
                                            x-model="row.direction" x-bind:disabled="!row.product_id">
                                            <option value="out">Remove (−)</option>
                                            <option value="in">Add (+)</option>
                                        </select>
                                    </td>

                                    <td class="px-2 py-2">
                                        <input type="number" step="0.001" min="0" class="form-input text-right"
                                            x-bind:name="`items[${index}][quantity]`" x-model="row.quantity"
                                            x-bind:data-qty="index" x-bind:disabled="!row.product_id">
                                    </td>

                                    <td class="px-2 py-2">
                                        <input type="number" step="0.01" min="0" class="form-input text-right"
                                            x-bind:name="`items[${index}][unit_cost]`" x-model="row.unit_cost"
                                            x-bind:disabled="!row.product_id">
                                    </td>

                                    <td class="px-2 py-3 text-right text-sm">
                                        <template x-if="row.product">
                                            <span class="font-semibold"
                                                x-bind:class="resultingStock(row) < 0 ? 'text-red-600' : 'text-slate-800'"
                                                x-text="display(resultingStock(row))"></span>
                                        </template>
                                        <template x-if="!row.product"><span class="text-slate-300">—</span></template>
                                    </td>

                                    <td class="px-2 py-3 text-right text-sm font-semibold text-slate-800">
                                        <span x-text="row.product_id ? display(lineValue(row)) : '—'"></span>
                                    </td>

                                    <td class="px-2 py-3 text-right">
                                        <button type="button" x-on:click="removeRow(index)"
                                            class="rounded p-1 text-slate-300 transition hover:bg-red-50 hover:text-red-600"
                                            title="Remove line">✕</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                    <x-button type="button" variant="secondary" size="sm" x-on:click="addRow()">+ Add line</x-button>
                    <p class="text-sm text-slate-600">
                        Total value affected
                        <span class="ml-1 font-semibold text-slate-900">{{ config('inventory.currency_symbol') }}<span x-text="display(totalValue)"></span></span>
                    </p>
                </div>
            </x-form-section>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <x-form-section title="Notes" icon="≡" description="Explain the adjustment for the audit trail.">
                        <x-field name="notes">
                            <x-textarea name="notes" id="notes" rows="4"
                                placeholder="e.g. Cold room failure overnight — milk and curd written off.">{{ old('notes') }}</x-textarea>
                        </x-field>
                    </x-form-section>
                </div>

                <div class="flex flex-col gap-2 self-start">
                    <x-button type="submit" x-bind:disabled="filledRows.length === 0">Post adjustment</x-button>
                    <x-button :href="route('stock-adjustments.index')" variant="secondary">Cancel</x-button>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
