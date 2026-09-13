<x-layouts.platform :title="$plan->exists ? 'Edit plan' : 'New plan'">
    <div class="mb-6">
        <a href="{{ route('platform.plans.index') }}" class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-brand-700">&larr; Plans</a>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ $plan->exists ? 'Edit '.$plan->name : 'New plan' }}</h1>
    </div>

    <form method="POST" action="{{ $plan->exists ? route('platform.plans.update', $plan) : route('platform.plans.store') }}"
        class="max-w-2xl space-y-6">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <x-form-section title="Plan" icon="¤">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-3" label="Name" name="name" required>
                    <x-input name="name" id="name" value="{{ old('name', $plan->name) }}" required placeholder="e.g. Standard" />
                </x-field>

                <x-field class="sm:col-span-3" label="Short code" name="slug" hint="Used in the address bar; filled in from the name.">
                    <x-input name="slug" id="slug" value="{{ old('slug', $plan->slug) }}" class="font-mono" />
                </x-field>

                <x-field class="sm:col-span-3" label="Price" name="monthly_price" required hint="Per period — or once, for a lifetime plan.">
                    <x-input type="number" step="0.01" min="0" name="monthly_price" id="monthly_price"
                        value="{{ old('monthly_price', $plan->monthly_price ?? 0) }}" required class="text-right" />
                </x-field>

                <x-field class="sm:col-span-3" label="Charged" name="billing_period" required>
                    <x-select name="billing_period" id="billing_period">
                        @foreach (\App\Enums\BillingPeriod::cases() as $period)
                            <option value="{{ $period->value }}"
                                @selected(old('billing_period', $plan->billing_period?->value ?? 'monthly') === $period->value)>
                                {{ $period->label() }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field class="sm:col-span-3" label="Currency" name="currency_code" required>
                    <x-select name="currency_code" id="currency_code">
                        @foreach (config('tenancy.currencies') as $code => $currency)
                            <option value="{{ $code }}" @selected(old('currency_code', $plan->currency_code) === $code)>
                                {{ $code }} — {{ $currency['name'] }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <div class="sm:col-span-6">
                    <p class="form-label">Price in other currencies</p>
                    <p class="form-hint mb-3">
                        Set by hand — no exchange rates, so a price only changes when you change it. Leave one blank and
                        that currency is charged the base price above.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (config('tenancy.pricing_currencies') as $code)
                            @continue($code === old('currency_code', $plan->currency_code ?? config('tenancy.base_currency')))
                            @php $existing = $plan->prices->firstWhere('currency_code', $code); @endphp
                            <x-field :label="$code" :name="'prices.'.$code">
                                <x-input type="number" step="0.01" min="0" :name="'prices['.$code.']'" :id="'prices_'.$code"
                                    value="{{ old('prices.'.$code, $existing?->amount) }}" class="text-right"
                                    placeholder="base price" />
                            </x-field>
                        @endforeach
                    </div>
                </div>

                <x-field class="sm:col-span-6" label="What it includes" name="description">
                    <x-input name="description" id="description" value="{{ old('description', $plan->description) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Sort order" name="sort_order">
                    <x-input type="number" min="0" name="sort_order" id="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" />
                </x-field>

                <div class="sm:col-span-4 sm:pt-7">
                    <x-checkbox name="is_active" label="Offered to new stores" :checked="old('is_active', $plan->is_active ?? true)" />
                </div>
            </div>
        </x-form-section>

        <div class="flex gap-2">
            <x-button type="submit">{{ $plan->exists ? 'Save changes' : 'Create plan' }}</x-button>
            <x-button :href="route('platform.plans.index')" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-layouts.platform>
