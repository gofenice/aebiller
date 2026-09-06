<x-layouts.app :title="$supplier->exists ? 'Edit supplier' : 'New supplier'">
    <x-page-header :title="$supplier->exists ? 'Edit '.$supplier->name : 'New supplier'" :back="route('suppliers.index')" />

    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}"
        class="max-w-4xl space-y-6">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif

        <x-form-section title="Supplier details" icon="◎">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-2" label="Supplier code" name="code" required>
                    <x-input name="code" id="code" value="{{ old('code', $supplier->code) }}" required class="font-mono" />
                </x-field>

                <x-field class="sm:col-span-4" label="Business name" name="name" required>
                    <x-input name="name" id="name" value="{{ old('name', $supplier->name) }}" required
                        placeholder="e.g. Sunrise Distributors" />
                </x-field>

                <x-field class="sm:col-span-2" label="Contact person" name="contact_person">
                    <x-input name="contact_person" id="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Phone" name="phone">
                    <x-input name="phone" id="phone" value="{{ old('phone', $supplier->phone) }}" placeholder="e.g. 055 123 4567" />
                </x-field>

                <x-field class="sm:col-span-2" label="Email" name="email">
                    <x-input type="email" name="email" id="email" value="{{ old('email', $supplier->email) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="VAT number" name="vat_number">
                    <x-input name="vat_number" id="vat_number" value="{{ old('vat_number', $supplier->vat_number) }}" class="font-mono" />
                </x-field>

                <x-field class="sm:col-span-2" label="Payment terms (days)" name="payment_terms_days"
                    hint="0 for cash on delivery.">
                    <x-input type="number" min="0" name="payment_terms_days" id="payment_terms_days"
                        value="{{ old('payment_terms_days', $supplier->payment_terms_days ?? 0) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Opening balance" name="opening_balance"
                    hint="Amount already owed to this supplier.">
                    <x-input type="number" step="0.01" min="0" name="opening_balance" id="opening_balance"
                        value="{{ old('opening_balance', $supplier->opening_balance ?? 0) }}" />
                </x-field>
            </div>
        </x-form-section>

        <x-form-section title="Address" icon="⌂">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-6" label="Address" name="address">
                    <x-input name="address" id="address" value="{{ old('address', $supplier->address) }}" />
                </x-field>
                <x-field class="sm:col-span-2" label="City" name="city">
                    <x-input name="city" id="city" value="{{ old('city', $supplier->city) }}" />
                </x-field>
                <x-field class="sm:col-span-2" label="State" name="state">
                    <x-input name="state" id="state" value="{{ old('state', $supplier->state) }}" />
                </x-field>
                <x-field class="sm:col-span-2" label="Postal code" name="pincode">
                    <x-input name="pincode" id="pincode" value="{{ old('pincode', $supplier->pincode) }}" />
                </x-field>
                <x-field class="sm:col-span-6" label="Notes" name="notes">
                    <x-textarea name="notes" id="notes">{{ old('notes', $supplier->notes) }}</x-textarea>
                </x-field>
                <div class="sm:col-span-6">
                    <x-checkbox name="is_active" label="Active" :checked="old('is_active', $supplier->is_active ?? true)" />
                </div>
            </div>
        </x-form-section>

        <div class="flex gap-2">
            <x-button type="submit">{{ $supplier->exists ? 'Save changes' : 'Create supplier' }}</x-button>
            <x-button :href="route('suppliers.index')" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-layouts.app>
