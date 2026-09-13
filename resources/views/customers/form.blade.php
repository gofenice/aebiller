<x-layouts.app :title="$customer->exists ? 'Edit member' : 'New member'">
    <x-page-header :title="$customer->exists ? 'Edit '.$customer->name : 'New loyalty member'"
        :description="$customer->exists ? null : 'A card number and the '.$settings->welcome_bonus.'-point welcome bonus are issued as soon as you save.'"
        :back="$customer->exists ? route('customers.show', $customer) : route('customers.index')" />

    <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}"
        class="max-w-4xl space-y-6">
        @csrf
        @if ($customer->exists) @method('PUT') @endif

        <x-form-section title="Member details" icon="★">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-3" label="Full name" name="name" required>
                    <x-input name="name" id="name" value="{{ old('name', $customer->name) }}" required autofocus
                        placeholder="As it should appear on the card" />
                </x-field>

                <x-field class="sm:col-span-3" label="Mobile number" name="phone" required
                    hint="The member can give this at the till instead of the card.">
                    <x-input type="tel" name="phone" id="phone" value="{{ old('phone', $customer->exists ? $customer->formattedPhone() : '') }}"
                        required placeholder="e.g. 055 123 4567" />
                </x-field>

                <x-field class="sm:col-span-3" label="Email" name="email">
                    <x-input type="email" name="email" id="email" value="{{ old('email', $customer->email) }}" />
                </x-field>

                <x-field class="sm:col-span-3" label="Date of birth" name="birth_date"
                    :hint="$settings->birthday_bonus > 0 ? 'Earns a '.$settings->birthday_bonus.'-point birthday bonus every year.' : null">
                    <x-input type="date" name="birth_date" id="birth_date" max="{{ now()->subDay()->toDateString() }}"
                        value="{{ old('birth_date', $customer->birth_date?->toDateString()) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="City / area" name="city">
                    <x-input name="city" id="city" value="{{ old('city', $customer->city) }}" />
                </x-field>

                <x-field class="sm:col-span-4" label="Address" name="address" hint="For home delivery.">
                    <x-input name="address" id="address" value="{{ old('address', $customer->address) }}" />
                </x-field>
            </div>
        </x-form-section>

        <x-form-section title="Preferences" icon="✉">
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    <x-checkbox name="marketing_opt_in" label="Happy to receive offers"
                        hint="Only contact members who have ticked this."
                        :checked="old('marketing_opt_in', $customer->marketing_opt_in)" />
                </div>
                @if ($customer->exists)
                    <div class="sm:col-span-3">
                        <x-checkbox name="is_active" label="Membership active"
                            hint="Untick to put the membership on hold: the card stops earning and redeeming."
                            :checked="old('is_active', $customer->is_active)" />
                    </div>
                @endif
                <x-field class="sm:col-span-6" label="Notes" name="notes" hint="Only staff see this.">
                    <x-textarea name="notes" id="notes">{{ old('notes', $customer->notes) }}</x-textarea>
                </x-field>
            </div>
        </x-form-section>

        <div class="flex gap-2">
            <x-button type="submit">{{ $customer->exists ? 'Save changes' : 'Enrol member' }}</x-button>
            <x-button :href="$customer->exists ? route('customers.show', $customer) : route('customers.index')" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-layouts.app>
