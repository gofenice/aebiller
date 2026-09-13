<x-layouts.platform :title="$store->exists ? 'Edit store' : 'New store'">
    @php
        $currencies = config('tenancy.currencies');
        $central = config('tenancy.central_domain');
    @endphp

    <div class="mb-6">
        <a href="{{ $store->exists ? route('platform.stores.show', $store) : route('platform.stores.index') }}"
            class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-brand-700">&larr; Back</a>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ $store->exists ? 'Edit '.$store->name : 'New store' }}</h1>
        @unless ($store->exists)
            <p class="mt-1 text-sm text-slate-500">
                The store gets its own address, an owner sign-in, and starter units, expense categories and loyalty tiers.
            </p>
        @endunless
    </div>

    <form method="POST"
        action="{{ $store->exists ? route('platform.stores.update', $store) : route('platform.stores.store') }}"
        class="max-w-4xl space-y-6"
        x-data="{
            name: {{ Js::from(old('name', $store->name)) }},
            slug: {{ Js::from(old('slug', $store->slug)) }},
            currency: {{ Js::from(old('currency_code', $store->currency_code)) }},
            symbols: {{ Js::from(collect($currencies)->map(fn ($currency) => $currency['symbol'])) }},
            touchedSlug: {{ $store->exists ? 'true' : 'false' }},
            suggestSlug() {
                if (this.touchedSlug) return;
                this.slug = this.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
            },
        }">
        @csrf
        @if ($store->exists) @method('PUT') @endif

        <x-form-section title="The shop" icon="◧">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-3" label="Shop name" name="name" required hint="Shown on its screens, receipts and loyalty cards.">
                    <x-input name="name" id="name" x-model="name" x-on:input="suggestSlug()" required placeholder="e.g. Fathima Super Market" />
                </x-field>

                <x-field class="sm:col-span-3" label="Address (subdomain)" name="slug" required>
                    <div class="flex items-center gap-1">
                        <x-input name="slug" id="slug" x-model="slug" x-on:input="touchedSlug = true" required class="font-mono" placeholder="fathima" />
                        <span class="shrink-0 font-mono text-sm text-slate-500">.{{ $central }}</span>
                    </div>
                    <p class="form-hint">Lowercase letters, numbers and dashes. This cannot be changed casually later — staff bookmark it.</p>
                </x-field>

                <x-field class="sm:col-span-6" label="Registered / legal name" name="legal_name" hint="If it differs from the trading name.">
                    <x-input name="legal_name" id="legal_name" value="{{ old('legal_name', $store->legal_name) }}" />
                </x-field>
            </div>
        </x-form-section>

        <x-form-section title="Who we deal with" icon="◍" description="The person the platform bills and contacts.">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-2" label="Owner name" name="owner_name" required>
                    <x-input name="owner_name" id="owner_name" value="{{ old('owner_name', $store->owner_name) }}" required />
                </x-field>
                <x-field class="sm:col-span-2" label="Owner email" name="owner_email" required>
                    <x-input type="email" name="owner_email" id="owner_email" value="{{ old('owner_email', $store->owner_email) }}" required />
                </x-field>
                <x-field class="sm:col-span-2" label="Owner mobile" name="owner_phone">
                    <x-input name="owner_phone" id="owner_phone" value="{{ old('owner_phone', $store->owner_phone) }}" />
                </x-field>
            </div>
        </x-form-section>

        <x-form-section title="Store settings" icon="⚙" description="What the shop's own screens and receipts use.">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-2" label="Currency" name="currency_code" required>
                    <select name="currency_code" id="currency_code" class="form-input pr-8" x-model="currency"
                        x-on:change="$refs.symbol.value = symbols[currency] ?? $refs.symbol.value">
                        @foreach ($currencies as $code => $currency)
                            <option value="{{ $code }}" @selected(old('currency_code', $store->currency_code) === $code)>
                                {{ $code }} — {{ $currency['name'] }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field class="sm:col-span-2" label="Symbol shown" name="currency_symbol" required hint="Printed before every amount.">
                    <x-input name="currency_symbol" id="currency_symbol" x-ref="symbol"
                        value="{{ old('currency_symbol', $store->currency_symbol) }}" required />
                </x-field>

                <x-field class="sm:col-span-2" label="Time zone" name="timezone" required>
                    <x-select name="timezone" id="timezone">
                        @foreach (config('tenancy.timezones') as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $store->timezone) === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field class="sm:col-span-2" label="VAT number" name="vat_number" hint="Printed on the tax invoice.">
                    <x-input name="vat_number" id="vat_number" value="{{ old('vat_number', $store->vat_number) }}" class="font-mono" />
                </x-field>

                <x-field class="sm:col-span-2" label="Shop phone" name="phone">
                    <x-input name="phone" id="phone" value="{{ old('phone', $store->phone) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Expiry warning (days)" name="expiry_alert_days" required
                    hint="How early stock going off is flagged.">
                    <x-input type="number" min="1" max="365" name="expiry_alert_days" id="expiry_alert_days"
                        value="{{ old('expiry_alert_days', $store->expiry_alert_days) }}" required />
                </x-field>

                <x-field class="sm:col-span-4" label="Shop address" name="address">
                    <x-input name="address" id="address" value="{{ old('address', $store->address) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Shop email" name="email">
                    <x-input type="email" name="email" id="email" value="{{ old('email', $store->email) }}" />
                </x-field>

                <x-field class="sm:col-span-6" label="Notes" name="notes" hint="Only the platform sees this.">
                    <x-textarea name="notes" id="notes">{{ old('notes', $store->notes) }}</x-textarea>
                </x-field>
            </div>
        </x-form-section>

        <x-form-section title="What they pay" icon="¤" description="Leave the fee empty to charge the plan's price.">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-2" label="Plan" name="plan_id">
                    <x-select name="plan_id" id="plan_id">
                        <option value="">No plan (free)</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((int) old('plan_id', $store->plan_id) === $plan->id)>
                                {{ $plan->name }} — {{ $plan->is_free ? 'free, never invoiced' : $plan->priceLabel() }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field class="sm:col-span-2" label="Agreed monthly fee" name="monthly_fee" hint="Only if it differs from the plan.">
                    <x-input type="number" step="0.01" min="0" name="monthly_fee" id="monthly_fee"
                        value="{{ old('monthly_fee', $store->monthly_fee) }}" placeholder="Plan price" />
                </x-field>

                <x-field class="sm:col-span-2" label="Billed in" name="billing_currency" hint="Defaults to the plan's currency.">
                    <x-select name="billing_currency" id="billing_currency">
                        <option value="">Same as plan</option>
                        @foreach (config('tenancy.currencies') as $code => $currency)
                            <option value="{{ $code }}" @selected(old('billing_currency', $store->billing_currency) === $code)>{{ $code }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field class="sm:col-span-2" label="Billing period" name="billing_period" hint="Leave as the plan's own cycle unless agreed otherwise.">
                    <x-select name="billing_period" id="billing_period">
                        <option value="">Same as plan</option>
                        @foreach (\App\Enums\BillingPeriod::cases() as $period)
                            <option value="{{ $period->value }}" @selected(old('billing_period', $store->billing_period?->value) === $period->value)>
                                {{ $period->label() }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field class="sm:col-span-2" label="Renew on day" name="billing_day" hint="Day of the month, 1–28.">
                    <x-input type="number" min="1" max="28" name="billing_day" id="billing_day"
                        value="{{ old('billing_day', $store->billing_day ?? 1) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Tell them this early (days)" name="invoice_lead_days"
                    hint="The invoice appears this many days before the renewal date.">
                    <x-input type="number" min="0" max="60" name="invoice_lead_days" id="invoice_lead_days"
                        value="{{ old('invoice_lead_days', $store->invoice_lead_days ?? 7) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Next renewal date" name="next_invoice_on" hint="Empty means never invoice again.">
                    <x-input type="date" name="next_invoice_on" id="next_invoice_on"
                        value="{{ old('next_invoice_on', $store->next_invoice_on?->toDateString()) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Grace days before closing" name="grace_days"
                    hint="Days after the due date before the shop is locked.">
                    <x-input type="number" min="0" max="90" name="grace_days" id="grace_days"
                        value="{{ old('grace_days', $store->grace_days ?? 7) }}" />
                </x-field>

                <div class="sm:col-span-3">
                    <x-checkbox name="auto_suspend" label="Close the store automatically once the grace period runs out"
                        hint="Untick to only show them a warning and decide by hand."
                        :checked="old('auto_suspend', $store->auto_suspend ?? true)" />
                </div>

                <div class="sm:col-span-3">
                    <x-checkbox name="prorate_first_invoice" label="Charge only part of the first month"
                        hint="For a shop joining mid-month: the first invoice covers the days left, and full months follow. Monthly plans only."
                        :checked="old('prorate_first_invoice', $store->prorate_first_invoice ?? true)" />
                </div>
            </div>
        </x-form-section>

        @unless ($store->exists)
            <x-form-section title="Owner's sign-in" icon="🔑"
                description="The shop's first account, with full rights inside their own store.">
                <div class="grid gap-4 sm:grid-cols-6">
                    <x-field class="sm:col-span-2" label="Name" name="admin_name" required>
                        <x-input name="admin_name" id="admin_name" value="{{ old('admin_name') }}" required />
                    </x-field>
                    <x-field class="sm:col-span-2" label="Sign-in email" name="admin_email" required>
                        <x-input type="email" name="admin_email" id="admin_email" value="{{ old('admin_email') }}" required />
                    </x-field>
                    <x-field class="sm:col-span-2" label="Password" name="admin_password"
                        hint="Leave empty and one is generated, shown to you once.">
                        <x-input name="admin_password" id="admin_password" autocomplete="new-password" />
                    </x-field>
                </div>
            </x-form-section>
        @endunless

        <div class="flex gap-2">
            <x-button type="submit">{{ $store->exists ? 'Save changes' : 'Create store' }}</x-button>
            <x-button :href="$store->exists ? route('platform.stores.show', $store) : route('platform.stores.index')" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-layouts.platform>
