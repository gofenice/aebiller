<x-layouts.app title="Stock entry">
    <x-page-header title="Stock in" :description="'Goods receipt · '.$nextReference"
        :back="route('stock-entries.index')" />

    <form method="POST" action="{{ route('stock-entries.store') }}"
        x-data="stockEntryForm({
            lookupUrl: '{{ route('products.lookup') }}',
            otherCharges: {{ (float) old('other_charges', 0) }},
            rows: {{ Js::from($oldRows) }},
        })">
        @csrf

        <div class="space-y-6">
            <x-form-section title="Document" icon="▤" description="Who supplied the goods and against which invoice.">
                <div class="grid gap-4 sm:grid-cols-6">
                    <x-field class="sm:col-span-2" label="Entry type" name="type" required>
                        <x-select name="type" id="type" required>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('type', $selectedType->value) === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field class="sm:col-span-2" label="Entry date" name="entry_date" required>
                        <x-input type="date" name="entry_date" id="entry_date"
                            value="{{ old('entry_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required />
                    </x-field>

                    <x-field class="sm:col-span-2" label="Supplier" name="supplier_id"
                        hint="Required for purchases.">
                        <x-select name="supplier_id" id="supplier_id">
                            <option value="">— Select supplier —</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field class="sm:col-span-2" label="Supplier invoice no." name="invoice_number">
                        <x-input name="invoice_number" id="invoice_number" value="{{ old('invoice_number') }}"
                            class="font-mono" placeholder="e.g. INV-3391" />
                    </x-field>

                    <x-field class="sm:col-span-2" label="Invoice date" name="invoice_date">
                        <x-input type="date" name="invoice_date" id="invoice_date" value="{{ old('invoice_date') }}"
                            max="{{ now()->toDateString() }}" />
                    </x-field>

                    <x-field class="sm:col-span-2" label="Other charges" name="other_charges"
                        hint="Freight, loading, etc.">
                        <x-input type="number" step="0.01" min="0" name="other_charges" id="other_charges"
                            x-model.number="otherCharges" value="{{ old('other_charges', 0) }}" />
                    </x-field>
                </div>
            </x-form-section>

            <x-form-section title="Products received" icon="↓"
                description="Search by name, SKU or barcode. A new line opens as soon as you pick a product.">
                <div class="overflow-x-auto" x-on:click.outside="closePicker()">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-slate-200">
                                <th class="table-head w-8">#</th>
                                <th class="table-head min-w-64">Product</th>
                                <th class="table-head w-28 text-right">Quantity</th>
                                <th class="table-head w-24 text-right">Free</th>
                                <th class="table-head w-28 text-right">Rate</th>
                                <th class="table-head w-20 text-right">Disc %</th>
                                <th class="table-head w-20 text-right">VAT %</th>
                                <th class="table-head w-28 text-right">Amount</th>
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
                                                · on hand <span x-text="row.product.current_stock"></span>
                                                <span x-text="row.product.unit"></span>
                                            </p>
                                        </template>

                                        {{-- Typeahead results --}}
                                        <template x-if="openRow === index && results.length">
                                        <div class="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                            <template x-for="result in results" :key="result.id">
                                                <button type="button" x-on:click="choose(index, result)"
                                                    class="flex w-full items-start justify-between gap-3 px-3 py-2 text-left hover:bg-brand-50">
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-sm font-medium text-slate-800" x-text="result.name"></span>
                                                        <span class="block font-mono text-xs text-slate-400" x-text="result.sku"></span>
                                                    </span>
                                                    <span class="shrink-0 text-right text-xs text-slate-500">
                                                        <span class="block">on hand <span x-text="result.current_stock"></span> <span x-text="result.unit"></span></span>
                                                        <span class="block">cost <span x-text="result.cost_price"></span></span>
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                        </template>

                                        {{-- Batch / expiry / price row --}}
                                        <template x-if="row.product && (row.product.track_batches || row.product.track_expiry)">
                                            <div class="mt-2 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-2 sm:grid-cols-3">
                                                <label class="block">
                                                    <span class="mb-0.5 block text-[11px] font-medium text-slate-500">Batch no.</span>
                                                    <input type="text" class="form-input py-1.5 text-xs"
                                                        x-bind:name="`items[${index}][batch_number]`" x-model="row.batch_number">
                                                </label>
                                                <label class="block">
                                                    <span class="mb-0.5 block text-[11px] font-medium text-slate-500">Mfg date</span>
                                                    <input type="date" class="form-input py-1.5 text-xs" max="{{ now()->toDateString() }}"
                                                        x-bind:name="`items[${index}][manufactured_on]`" x-model="row.manufactured_on">
                                                </label>
                                                <label class="block">
                                                    <span class="mb-0.5 block text-[11px] font-medium text-slate-500">Expires on</span>
                                                    <input type="date" class="form-input py-1.5 text-xs" min="{{ now()->addDay()->toDateString() }}"
                                                        x-bind:name="`items[${index}][expires_on]`" x-model="row.expires_on">
                                                </label>
                                            </div>
                                        </template>

                                        <template x-if="row.product">
                                            <label class="mt-2 flex items-center gap-2">
                                                <span class="text-[11px] font-medium text-slate-500">New selling price</span>
                                                <input type="number" step="0.01" min="0" class="form-input w-28 py-1 text-xs"
                                                    x-bind:name="`items[${index}][selling_price]`" x-model="row.selling_price">
                                                <span class="text-[11px] text-slate-400">leave as is to keep the current price</span>
                                            </label>
                                        </template>
                                    </td>

                                    <td class="px-2 py-2">
                                        <input type="number" step="0.001" min="0" class="form-input text-right"
                                            x-bind:name="`items[${index}][quantity]`" x-model="row.quantity"
                                            x-bind:data-qty="index" x-bind:disabled="!row.product_id">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" step="0.001" min="0" class="form-input text-right"
                                            x-bind:name="`items[${index}][free_quantity]`" x-model="row.free_quantity"
                                            x-bind:disabled="!row.product_id" placeholder="0">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" step="0.01" min="0" class="form-input text-right"
                                            x-bind:name="`items[${index}][unit_cost]`" x-model="row.unit_cost"
                                            x-bind:disabled="!row.product_id">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" step="0.01" min="0" max="100" class="form-input text-right"
                                            x-bind:name="`items[${index}][discount_percent]`" x-model="row.discount_percent"
                                            x-bind:disabled="!row.product_id" placeholder="0">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" step="0.01" min="0" max="100" class="form-input text-right"
                                            x-bind:name="`items[${index}][tax_percent]`" x-model="row.tax_percent"
                                            x-bind:disabled="!row.product_id" placeholder="0">
                                    </td>
                                    <td class="px-2 py-3 text-right text-sm font-semibold text-slate-800">
                                        <span x-text="row.product_id ? lineDisplay(row) : '—'"></span>
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
                    <p class="text-xs text-slate-500">
                        <span x-text="filledRows.length"></span> product(s) ·
                        <span x-text="display(totalUnits)"></span> units received
                    </p>
                </div>
            </x-form-section>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <x-form-section title="Notes" icon="≡">
                        <x-field name="notes">
                            <x-textarea name="notes" id="notes" rows="4"
                                placeholder="Anything worth recording about this delivery — short supply, damaged cartons, etc.">{{ old('notes') }}</x-textarea>
                        </x-field>
                    </x-form-section>
                </div>

                <div class="space-y-4">
                    <x-form-section title="Totals" icon="¤">
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Subtotal</dt>
                                <dd class="font-medium text-slate-800">{{ config('inventory.currency_symbol') }}<span x-text="display(subtotal)"></span></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Discount</dt>
                                <dd class="font-medium text-slate-800">− {{ config('inventory.currency_symbol') }}<span x-text="display(discountTotal)"></span></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">VAT</dt>
                                <dd class="font-medium text-slate-800">{{ config('inventory.currency_symbol') }}<span x-text="display(taxTotal)"></span></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Other charges</dt>
                                <dd class="font-medium text-slate-800">{{ config('inventory.currency_symbol') }}<span x-text="display(otherCharges)"></span></dd>
                            </div>
                            <div class="flex justify-between border-t border-slate-200 pt-2.5">
                                <dt class="font-semibold text-slate-900">Grand total</dt>
                                <dd class="text-base font-semibold text-brand-700">{{ config('inventory.currency_symbol') }}<span x-text="display(grandTotal)"></span></dd>
                            </div>
                        </dl>
                    </x-form-section>

                    <div class="flex flex-col gap-2">
                        <x-button type="submit" x-bind:disabled="filledRows.length === 0">Post stock entry</x-button>
                        <x-button :href="route('stock-entries.index')" variant="secondary">Cancel</x-button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
