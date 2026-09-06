@php
    $isEdit = $expense->exists;
    $symbol = config('inventory.currency_symbol');
@endphp

<x-layouts.app :title="$isEdit ? 'Edit expense' : 'New expense'">
    <x-page-header :title="$isEdit ? 'Edit '.$expense->reference_no : 'Record an expense'"
        :description="$isEdit ? null : 'Reference '.$nextReference"
        :back="route('expenses.index')" />

    <form method="POST"
        action="{{ $isEdit ? route('expenses.update', $expense) : route('expenses.store') }}"
        enctype="multipart/form-data"
        class="max-w-4xl space-y-6"
        x-data="{
            amount: Number('{{ old('amount', $expense->amount ?? 0) }}') || 0,
            vatRate: Number('{{ old('vat_rate', $expense->vat_rate ?? 0) }}') || 0,
            isPaid: {{ old('is_paid', $expense->is_paid ?? true) ? 'true' : 'false' }},
            supplierId: '{{ old('supplier_id', $expense->supplier_id) }}',
            get vatAmount() { return (this.amount * this.vatRate / 100).toFixed(2) },
            get total() { return (this.amount * (1 + this.vatRate / 100)).toFixed(2) },
        }">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-form-section title="What was the money for?" icon="¤"
            description="Supplier bills, staff tea, travel, repairs — anything the shop pays out.">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-2" label="Date" name="expense_date" required>
                    <x-input type="date" name="expense_date" id="expense_date" required
                        max="{{ now()->toDateString() }}"
                        value="{{ old('expense_date', $expense->expense_date?->toDateString() ?? now()->toDateString()) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Category" name="expense_category_id" required>
                    <x-select name="expense_category_id" id="expense_category_id" required>
                        <option value="">Select a category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field class="sm:col-span-2" label="Bill / invoice no." name="invoice_number">
                    <x-input name="invoice_number" id="invoice_number" class="font-mono"
                        value="{{ old('invoice_number', $expense->invoice_number) }}" placeholder="Optional" />
                </x-field>

                <x-field class="sm:col-span-6" label="Description" name="description" required
                    hint="What it was, in the words you would use to explain it later.">
                    <x-input name="description" id="description" required
                        value="{{ old('description', $expense->description) }}"
                        placeholder="e.g. Tea and sugar for the staff room" />
                </x-field>
            </div>
        </x-form-section>

        <x-form-section title="Who was paid?" icon="◎"
            description="Pick a registered supplier, or just type a name for one-off payments.">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-3" label="Supplier" name="supplier_id">
                    <x-select name="supplier_id" id="supplier_id" x-model="supplierId">
                        <option value="">— Not a registered supplier —</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $expense->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field class="sm:col-span-3" label="Paid to" name="payee"
                    hint="Used when there is no supplier record, e.g. the tea shop or a taxi.">
                    <x-input name="payee" id="payee" value="{{ old('payee', $expense->payee) }}"
                        x-bind:disabled="supplierId !== ''" placeholder="e.g. Al Nakheel Cafeteria" />
                </x-field>

                <x-field class="sm:col-span-6" label="Against a goods receipt" name="stock_entry_id"
                    hint="Link a supplier bill to the delivery it paid for. Leave blank for everything else.">
                    <x-select name="stock_entry_id" id="stock_entry_id">
                        <option value="">— None —</option>
                        @foreach ($recentEntries as $entry)
                            <option value="{{ $entry->id }}" @selected(old('stock_entry_id', $expense->stock_entry_id) == $entry->id)>
                                {{ $entry->reference_no }} · {{ $entry->supplier?->name ?? $entry->type->label() }} ·
                                {{ $symbol }}{{ number_format((float) $entry->grand_total, 2) }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>
            </div>
        </x-form-section>

        <x-form-section title="Amount" icon="≡" description="Enter the net amount and the VAT is worked out for you.">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-2" label="Amount excluding VAT" name="amount" required>
                    <x-input type="number" step="0.01" min="0" name="amount" id="amount" required
                        x-model.number="amount" value="{{ old('amount', $expense->amount ?? '') }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="VAT rate (%)" name="vat_rate" required
                    hint="0% for anything with no tax invoice.">
                    <x-select name="vat_rate" id="vat_rate" required x-model.number="vatRate">
                        @foreach ($taxRates as $rate)
                            <option value="{{ $rate }}" @selected((float) old('vat_rate', $expense->vat_rate ?? 0) === (float) $rate)>{{ $rate }}%</option>
                        @endforeach
                    </x-select>
                </x-field>

                <div class="sm:col-span-2">
                    <span class="form-label">Total to pay</span>
                    <div class="form-input flex items-baseline justify-between bg-slate-50">
                        <span class="text-xs text-slate-500">VAT {{ $symbol }}<span x-text="vatAmount"></span></span>
                        <span class="text-base font-semibold text-slate-900">{{ $symbol }}<span x-text="total"></span></span>
                    </div>
                </div>

                <x-field class="sm:col-span-2" label="Paid by" name="payment_method" required>
                    <x-select name="payment_method" id="payment_method" required>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->value }}" @selected(old('payment_method', $expense->payment_method?->value) === $method->value)>
                                {{ $method->shortLabel() }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <div class="sm:col-span-2 sm:pt-6">
                    <x-checkbox name="is_paid" label="Already paid" x-model="isPaid"
                        hint="Leave off to track it as still owed."
                        :checked="old('is_paid', $expense->is_paid ?? true)" />
                </div>

                <x-field class="sm:col-span-2" label="Paid on" name="paid_on" x-show="isPaid" x-cloak
                    hint="Defaults to the expense date.">
                    <x-input type="date" name="paid_on" id="paid_on" max="{{ now()->toDateString() }}"
                        value="{{ old('paid_on', $expense->paid_on?->toDateString()) }}" />
                </x-field>
            </div>
        </x-form-section>

        <x-form-section title="Receipt &amp; notes" icon="◧">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Attach the receipt" name="attachment" hint="JPG, PNG or PDF, up to 4 MB.">
                    @if ($expense->attachment_path)
                        <p class="mb-2 text-xs">
                            <a href="{{ Storage::url($expense->attachment_path) }}" target="_blank"
                                class="font-medium text-brand-700 hover:underline">View the current receipt</a>
                        </p>
                    @endif
                    <input type="file" name="attachment" id="attachment" accept="image/*,application/pdf"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-field>

                <x-field label="Notes" name="notes">
                    <x-textarea name="notes" id="notes">{{ old('notes', $expense->notes) }}</x-textarea>
                </x-field>
            </div>
        </x-form-section>

        <div class="flex gap-2">
            <x-button type="submit">{{ $isEdit ? 'Save changes' : 'Record expense' }}</x-button>
            <x-button :href="route('expenses.index')" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-layouts.app>
