@php
    /** @var \App\Models\Product $product */
    $isEdit = $product->exists;
    $currentType = old('type', $product->type?->value ?? 'packaged');
@endphp

<form method="POST"
    action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}"
    enctype="multipart/form-data"
    x-data="productForm({{ Js::from([
        'type' => $currentType,
        'cost' => (float) old('cost_price', $product->cost_price ?? 0),
        'sellingPrice' => (float) old('selling_price', $product->selling_price ?? 0),
        'taxRate' => (float) old('tax_rate', $product->tax_rate ?? 0),
        'trackBatches' => (bool) old('track_batches', $product->track_batches),
        'trackExpiry' => (bool) old('track_expiry', $product->track_expiry),
        'openingStock' => (float) old('opening_stock', 0),
    ]) }})">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- 1. Product type --}}
            @php
                $typeLocked = $isEdit && (float) $product->current_stock > 0;
            @endphp

            <x-form-section title="Product type" icon="◱"
                description="This decides how the item is counted, priced and sold at the till.">
                @if ($typeLocked)
                    <input type="hidden" name="type" value="{{ $currentType }}">
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (\App\Enums\ProductType::cases() as $productType)
                        <label class="relative flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                            x-bind:class="type === '{{ $productType->value }}'
                                ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500'
                                : 'border-slate-200 bg-white hover:border-slate-300'">
                            <input type="radio" name="type" value="{{ $productType->value }}" x-model="type"
                                class="mt-0.5 size-4 border-slate-300 text-brand-600 focus:ring-brand-500"
                                @disabled($typeLocked)>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900">{{ $productType->label() }}</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-slate-500">{{ $productType->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @if ($typeLocked)
                    <p class="form-hint">Type is locked while the product holds stock.</p>
                @endif
                @error('type')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </x-form-section>

            {{-- 2. Identification --}}
            <x-form-section title="Product details" icon="▤" description="What the item is called and how it is identified.">
                <div class="grid gap-4 sm:grid-cols-6">
                    <x-field class="sm:col-span-4" label="Product name" name="name" required
                        hint="e.g. Aashirvaad Select Atta, or Tomato (Local)">
                        <x-input name="name" id="name" value="{{ old('name', $product->name) }}" required
                            placeholder="Enter the product name" />
                    </x-field>

                    <x-field class="sm:col-span-2" label="Short name" name="short_name" hint="Shown on the till display">
                        <x-input name="short_name" id="short_name" value="{{ old('short_name', $product->short_name) }}"
                            placeholder="Optional" maxlength="60" />
                    </x-field>

                    <x-field class="sm:col-span-2" label="SKU / item code" name="sku" required>
                        <x-input name="sku" id="sku" value="{{ old('sku', $product->sku) }}" required class="font-mono" />
                    </x-field>

                    <x-field class="sm:col-span-2" label="Barcode" name="barcode"
                        hint="Scan the pack, or generate one for loose items.">
                        <div class="flex gap-2">
                            <x-input name="barcode" id="barcode" x-ref="barcode" class="font-mono"
                                value="{{ old('barcode', $product->barcode) }}" placeholder="Scan or type" />
                            <button type="button" x-on:click="generateBarcode()"
                                class="shrink-0 rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                Generate
                            </button>
                        </div>
                    </x-field>

                    <x-field class="sm:col-span-2" label="Category" name="category_id" required>
                        <x-select name="category_id" id="category_id" required>
                            <option value="">Select a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>
                                    {{ $category->parent ? $category->parent->name.' › ' : '' }}{{ $category->name }}
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field class="sm:col-span-3" label="Brand" name="brand_id" hint="Leave blank for unbranded produce.">
                        <x-select name="brand_id" id="brand_id">
                            <option value="">— None —</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field class="sm:col-span-3" label="Default supplier" name="supplier_id">
                        <x-select name="supplier_id" id="supplier_id">
                            <option value="">— None —</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id', $product->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field class="sm:col-span-6" label="Description" name="description">
                        <x-textarea name="description" id="description" placeholder="Optional notes about the product">{{ old('description', $product->description) }}</x-textarea>
                    </x-field>
                </div>
            </x-form-section>

            {{-- 3. Packing & unit --}}
            <x-form-section title="Packing & unit of measure" icon="⚖"
                description="How the item leaves the shelf — per piece for packets, per kilo for loose produce.">
                <div class="grid gap-4 sm:grid-cols-6">
                    <x-field class="sm:col-span-2" label="Selling unit" name="unit_id" required
                        hint="Unit used at the till and in reports.">
                        <x-select name="unit_id" id="unit_id" required>
                            <option value="">Select unit</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" @selected(old('unit_id', $product->unit_id) == $unit->id)>
                                    {{ $unit->name }} ({{ $unit->code }})
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <template x-if="!isLoose">
                        <div class="contents">
                            <x-field class="sm:col-span-2" label="Pack size" name="pack_size" required
                                hint="e.g. 500 for a 500 g pack">
                                <x-input type="number" step="0.001" min="0" name="pack_size" id="pack_size"
                                    value="{{ old('pack_size', $product->pack_size) }}" placeholder="500" />
                            </x-field>

                            <x-field class="sm:col-span-2" label="Pack unit" name="pack_unit_id">
                                <x-select name="pack_unit_id" id="pack_unit_id">
                                    <option value="">Select unit</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" @selected(old('pack_unit_id', $product->pack_unit_id) == $unit->id)>
                                            {{ $unit->name }} ({{ $unit->code }})
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-field>

                            <x-field class="sm:col-span-2" label="Units per case" name="units_per_case"
                                hint="Pieces in a supplier carton.">
                                <x-input type="number" min="1" name="units_per_case" id="units_per_case"
                                    value="{{ old('units_per_case', $product->units_per_case) }}" placeholder="e.g. 24" />
                            </x-field>
                        </div>
                    </template>

                    <template x-if="isLoose">
                        <div class="contents">
                            <x-field class="sm:col-span-2" label="Minimum sale quantity" name="min_sale_quantity"
                                hint="Smallest weight the scale should accept, e.g. 0.25 kg">
                                <x-input type="number" step="0.001" min="0" name="min_sale_quantity" id="min_sale_quantity"
                                    value="{{ old('min_sale_quantity', $product->min_sale_quantity) }}" placeholder="0.250" />
                            </x-field>

                            <x-field class="sm:col-span-2" label="Tare weight" name="tare_weight"
                                hint="Weight of the tray or bag, deducted at the scale.">
                                <x-input type="number" step="0.001" min="0" name="tare_weight" id="tare_weight"
                                    value="{{ old('tare_weight', $product->tare_weight) }}" placeholder="0.010" />
                            </x-field>

                            <x-field class="sm:col-span-2" label="Expected wastage %" name="wastage_percent"
                                hint="Trimming and spoilage allowance.">
                                <x-input type="number" step="0.01" min="0" max="100" name="wastage_percent" id="wastage_percent"
                                    value="{{ old('wastage_percent', $product->wastage_percent ?? 0) }}" placeholder="5" />
                            </x-field>
                        </div>
                    </template>

                    <div class="sm:col-span-6">
                        <x-checkbox name="is_weighable" label="Weighed at the counter"
                            hint="Print a scale label with weight and price instead of a fixed barcode."
                            :checked="old('is_weighable', $product->is_weighable)" />
                    </div>
                </div>
            </x-form-section>

            {{-- 4. Pricing --}}
            <x-form-section title="Pricing & VAT" icon="¤"
                description="Enter the price either way round — the other figure fills itself in from the VAT rate.">
                <div class="grid gap-4 sm:grid-cols-6">
                    <x-field class="sm:col-span-2" label="Cost price" name="cost_price" required
                        hint="Landed purchase price, excluding VAT.">
                        <x-input type="number" step="0.01" min="0" name="cost_price" id="cost_price" required
                            x-model.number="cost" value="{{ old('cost_price', $product->cost_price ?? 0) }}" />
                    </x-field>

                    <x-field class="sm:col-span-2" label="VAT rate (%)" name="tax_rate" required>
                        <x-select name="tax_rate" id="tax_rate" required
                            x-model.number="taxRate" x-on:change="onRateChange()">
                            @foreach ($taxRates as $rate)
                                <option value="{{ $rate }}">{{ $rate }}%</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field class="sm:col-span-2" label="HS code" name="hs_code">
                        <x-input name="hs_code" id="hs_code" value="{{ old('hs_code', $product->hs_code) }}"
                            class="font-mono" placeholder="e.g. 1006" />
                    </x-field>

                    {{-- Selling price, enterable from either side of the VAT --}}
                    <div class="sm:col-span-6">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <div class="grid items-end gap-3 sm:grid-cols-[1fr_auto_1fr_auto_1fr]">
                                <div>
                                    <label for="price_excl" class="form-label">Selling price excluding VAT</label>
                                    <x-input type="number" step="0.01" min="0" id="price_excl"
                                        x-model.number="priceExcl" x-on:input="fromExclusive()" />
                                </div>

                                <span class="hidden pb-2.5 text-center text-lg text-slate-400 sm:block">+</span>

                                <div>
                                    <span class="form-label">VAT amount</span>
                                    <div class="form-input bg-white text-right text-slate-600">
                                        {{ config('inventory.currency_symbol') }}<span x-text="money(vatAmount)"></span>
                                    </div>
                                </div>

                                <span class="hidden pb-2.5 text-center text-lg text-slate-400 sm:block">=</span>

                                <div>
                                    <label for="price_incl" class="form-label">
                                        Shelf price including VAT
                                        <span class="ml-1 rounded bg-brand-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-brand-700 uppercase">
                                            customer pays
                                        </span>
                                    </label>
                                    <x-input type="number" step="0.01" min="0" id="price_incl"
                                        x-model.number="priceIncl" x-on:input="fromInclusive()" />
                                </div>
                            </div>

                            {{-- The shelf price is what gets stored; the net figure is derived from it. --}}
                            <input type="hidden" name="selling_price" x-bind:value="money(priceIncl)">

                            <p class="mt-2 text-xs text-slate-500">
                                Fill in whichever you know — the other side works itself out from the VAT rate.
                            </p>

                            @error('selling_price')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="sm:col-span-6">
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm">
                            <span class="text-slate-500">Margin on net</span>
                            <span class="font-semibold" x-bind:class="margin === null ? 'text-slate-400' : (margin < 0 ? 'text-red-600' : 'text-emerald-600')">
                                <span x-text="margin === null ? '—' : margin + '%'"></span>
                            </span>
                            <span class="text-slate-300">|</span>
                            <span class="text-slate-500">Profit per unit</span>
                            <span class="font-semibold text-slate-800">{{ config('inventory.currency_symbol') }}<span x-text="profit"></span></span>
                        </div>
                    </div>
                </div>
            </x-form-section>

            {{-- 5. Stock control --}}
            <x-form-section title="Stock control" icon="▦" description="Reorder points, storage and where the item sits in store.">
                <div class="grid gap-4 sm:grid-cols-6">
                    @unless ($isEdit)
                        <x-field class="sm:col-span-2" label="Opening stock" name="opening_stock"
                            hint="Quantity on the shelf right now.">
                            <x-input type="number" step="0.001" min="0" name="opening_stock" id="opening_stock"
                                x-model.number="openingStock" value="{{ old('opening_stock', 0) }}" />
                        </x-field>
                    @else
                        <x-field class="sm:col-span-2" label="Current stock">
                            <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                <span class="font-semibold text-slate-900">@qty($product->current_stock)</span>
                                <span class="text-slate-500">{{ $product->unit?->code }}</span>
                            </div>
                            <p class="form-hint">Change it with a stock entry or adjustment.</p>
                        </x-field>
                    @endunless

                    <x-field class="sm:col-span-2" label="Reorder level" name="reorder_level"
                        hint="Alert when stock drops to this level.">
                        <x-input type="number" step="0.001" min="0" name="reorder_level" id="reorder_level"
                            value="{{ old('reorder_level', $product->reorder_level ?? 0) }}" />
                    </x-field>

                    <x-field class="sm:col-span-2" label="Maximum stock" name="max_stock_level"
                        hint="Shelf and backroom capacity.">
                        <x-input type="number" step="0.001" min="0" name="max_stock_level" id="max_stock_level"
                            value="{{ old('max_stock_level', $product->max_stock_level) }}" />
                    </x-field>

                    <x-field class="sm:col-span-2" label="Storage" name="storage_type" required>
                        <x-select name="storage_type" id="storage_type" required>
                            @foreach ($storageTypes as $storage)
                                <option value="{{ $storage->value }}" @selected(old('storage_type', $product->storage_type?->value) === $storage->value)>
                                    {{ $storage->label() }}
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field class="sm:col-span-4" label="Rack / shelf location" name="rack_location"
                        hint="Where staff will find it, e.g. Aisle 3 · Rack B2">
                        <x-input name="rack_location" id="rack_location" value="{{ old('rack_location', $product->rack_location) }}"
                            placeholder="Aisle 3 · Rack B2" />
                    </x-field>

                    @unless ($isEdit)
                        <div class="sm:col-span-6" x-show="openingStock > 0" x-cloak>
                            <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
                                Opening stock of <span class="font-semibold" x-text="openingStock"></span> will be posted to the
                                stock ledger at a value of
                                <span class="font-semibold">{{ config('inventory.currency_symbol') }}<span x-text="openingValue"></span></span>.
                            </div>
                        </div>
                    @endunless
                </div>
            </x-form-section>

            {{-- 6. Batch & expiry --}}
            <x-form-section title="Batch & expiry tracking" icon="⏱"
                description="Turn this on for anything perishable so near-expiry stock can be pulled in time.">
                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-3">
                        <x-checkbox name="track_batches" label="Track batch numbers" x-model="trackBatches"
                            hint="Record a batch/lot number on every goods receipt."
                            :checked="old('track_batches', $product->track_batches)" />
                    </div>

                    <div class="sm:col-span-3">
                        <x-checkbox name="track_expiry" label="Track expiry dates" x-model="trackExpiry"
                            hint="Stock is issued oldest-expiry-first (FEFO)."
                            :checked="old('track_expiry', $product->track_expiry)" />
                    </div>

                    <template x-if="trackExpiry || trackBatches">
                        <div class="contents">
                            <x-field class="sm:col-span-3" label="Shelf life (days)" name="shelf_life_days"
                                hint="Used to work out expiry when the supplier does not print one.">
                                <x-input type="number" min="1" name="shelf_life_days" id="shelf_life_days"
                                    value="{{ old('shelf_life_days', $product->shelf_life_days) }}" placeholder="e.g. 180" />
                            </x-field>

                            @unless ($isEdit)
                                <x-field class="sm:col-span-3" label="Opening stock expires on" name="opening_expires_on"
                                    hint="Expiry date of the stock you have on hand.">
                                    <x-input type="date" name="opening_expires_on" id="opening_expires_on"
                                        value="{{ old('opening_expires_on') }}" />
                                </x-field>
                            @endunless
                        </div>
                    </template>
                </div>
            </x-form-section>
        </div>

        {{-- Right rail --}}
        <div class="space-y-6">
            <x-form-section title="Status" icon="◉">
                <x-checkbox name="is_active" label="Active"
                    hint="Inactive products stay in reports but cannot be received or sold."
                    :checked="old('is_active', $product->is_active ?? true)" />
            </x-form-section>

            <x-form-section title="Product image" icon="◧">
                @if ($product->image_path)
                    <img src="{{ Storage::url($product->image_path) }}" alt="{{ $product->name }}"
                        class="mb-3 h-32 w-full rounded-lg border border-slate-200 object-cover">
                @endif
                <x-field name="image" hint="JPG or PNG, up to 2 MB.">
                    <input type="file" name="image" id="image" accept="image/*"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-field>
            </x-form-section>

            <x-form-section title="Summary" icon="≡">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Type</dt>
                        <dd class="font-medium text-slate-800" x-text="isLoose ? 'Loose / Weighed' : 'Packet'"></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Cost</dt>
                        <dd class="font-medium text-slate-800">{{ config('inventory.currency_symbol') }}<span x-text="money(cost)"></span></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Selling (excl. VAT)</dt>
                        <dd class="font-medium text-slate-800">{{ config('inventory.currency_symbol') }}<span x-text="money(priceExcl)"></span></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Customer pays</dt>
                        <dd class="font-medium text-slate-800">{{ config('inventory.currency_symbol') }}<span x-text="money(priceIncl)"></span></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Margin</dt>
                        <dd class="font-medium" x-bind:class="margin === null ? 'text-slate-400' : (margin < 0 ? 'text-red-600' : 'text-emerald-600')"
                            x-text="margin === null ? '—' : margin + '%'"></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Expiry tracked</dt>
                        <dd class="font-medium text-slate-800" x-text="trackExpiry ? 'Yes' : 'No'"></dd>
                    </div>
                </dl>
            </x-form-section>

            <div class="flex flex-col gap-2">
                <x-button type="submit">{{ $isEdit ? 'Save changes' : 'Save product' }}</x-button>
                <x-button :href="$isEdit ? route('products.show', $product) : route('products.index')" variant="secondary">Cancel</x-button>
            </div>
        </div>
    </div>
</form>
