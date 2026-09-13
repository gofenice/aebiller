<x-layouts.app title="Loyalty settings">
    @php
        $symbol = trim(config('inventory.currency_symbol'));
        $rows = old('tiers', $tiers->map(fn ($tier): array => [
            'id' => $tier->id,
            'name' => $tier->name,
            'min_spend' => (float) $tier->min_spend,
            'earn_multiplier' => (float) $tier->earn_multiplier,
            'card_theme' => $tier->card_theme->value,
            'perks' => $tier->perks,
            'remove' => false,
            'members' => $tier->customers_count,
        ])->all());
    @endphp

    <x-page-header title="Loyalty settings" description="The rules of the programme. Changes apply from the next bill.">
        <x-slot:actions>
            <x-button :href="route('customers.card-designs')" variant="secondary" target="_blank">▭ Preview card designs</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('loyalty.settings.update') }}" class="max-w-5xl space-y-6"
        x-data="{
            earn: {{ Js::from((float) old('points_per_currency', $settings->points_per_currency)) }},
            value: {{ Js::from((float) old('point_value', $settings->point_value)) }},
            rows: {{ Js::from(array_values($rows)) }},
            addTier() {
                this.rows.push({ id: null, name: '', min_spend: '', earn_multiplier: 1, card_theme: 'emerald', perks: '', remove: false, members: 0 });
            },
            get cashBack() {
                return (Number(this.earn || 0) * Number(this.value || 0) * 100).toFixed(2);
            },
        }">
        @csrf
        @method('PUT')

        <x-form-section title="Programme" icon="★">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-3" label="Programme name" name="program_name" required hint="Printed on the card and the receipt.">
                    <x-input name="program_name" id="program_name" value="{{ old('program_name', $settings->program_name) }}" required />
                </x-field>
                <div class="sm:col-span-3 sm:pt-7">
                    <x-checkbox name="is_enabled" label="Programme switched on"
                        hint="Switched off, cards still attach to bills but earn and redeem nothing."
                        :checked="old('is_enabled', $settings->is_enabled)" />
                </div>
            </div>
        </x-form-section>

        <x-form-section title="Earning and redeeming" icon="✦">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-field class="sm:col-span-3" label="Points per {{ $symbol }} 1 spent" name="points_per_currency" required
                    hint="Before the tier multiplier. Earned on the bill total, after discounts.">
                    <x-input type="number" step="0.01" min="0" name="points_per_currency" id="points_per_currency" x-model="earn"
                        value="{{ old('points_per_currency', (float) $settings->points_per_currency) }}" required />
                </x-field>
                <x-field class="sm:col-span-3" label="Value of 1 point ({{ $symbol }})" name="point_value" required
                    hint="What one point takes off a bill when redeemed.">
                    <x-input type="number" step="0.0001" min="0.0001" name="point_value" id="point_value" x-model="value"
                        value="{{ old('point_value', (float) $settings->point_value) }}" required />
                </x-field>
                <x-field class="sm:col-span-3" label="Minimum points to redeem" name="min_redeem_points" required>
                    <x-input type="number" step="1" min="1" name="min_redeem_points" id="min_redeem_points"
                        value="{{ old('min_redeem_points', $settings->min_redeem_points) }}" required />
                </x-field>
                <x-field class="sm:col-span-3" label="Points may pay for up to (% of the bill)" name="max_redeem_percent" required>
                    <x-input type="number" step="0.01" min="1" max="100" name="max_redeem_percent" id="max_redeem_percent"
                        value="{{ old('max_redeem_percent', (float) $settings->max_redeem_percent) }}" required />
                </x-field>
                <p class="rounded-lg bg-brand-50 px-3 py-2.5 text-sm text-brand-800 sm:col-span-6">
                    A {{ $symbol }} 100 bill earns <strong x-text="Math.floor(earn * 100)"></strong> points, worth
                    <strong>{{ $symbol }} <span x-text="(Math.floor(earn * 100) * value).toFixed(2)"></span></strong> —
                    <strong x-text="cashBack + '%'"></strong> back to the customer at the entry tier.
                </p>
            </div>
        </x-form-section>

        <x-form-section title="Bonuses and expiry" icon="⏱">
            <div class="grid gap-4 sm:grid-cols-4">
                <x-field label="Welcome bonus (points)" name="welcome_bonus" required>
                    <x-input type="number" step="1" min="0" name="welcome_bonus" id="welcome_bonus"
                        value="{{ old('welcome_bonus', $settings->welcome_bonus) }}" required />
                </x-field>
                <x-field label="Birthday bonus (points)" name="birthday_bonus" required hint="Credited overnight on the day.">
                    <x-input type="number" step="1" min="0" name="birthday_bonus" id="birthday_bonus"
                        value="{{ old('birthday_bonus', $settings->birthday_bonus) }}" required />
                </x-field>
                <x-field label="Points expire after (months)" name="points_expiry_months" required hint="0 means never.">
                    <x-input type="number" step="1" min="0" name="points_expiry_months" id="points_expiry_months"
                        value="{{ old('points_expiry_months', $settings->points_expiry_months) }}" required />
                </x-field>
                <x-field label="Tier based on spend over (months)" name="tier_window_months" required>
                    <x-input type="number" step="1" min="1" name="tier_window_months" id="tier_window_months"
                        value="{{ old('tier_window_months', $settings->tier_window_months) }}" required />
                </x-field>
            </div>
        </x-form-section>

        <x-form-section title="Tiers" icon="◆"
            description="Members move up and down automatically with their spend. The tier at 0 is where everyone starts.">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="table-head pl-0">Tier</th>
                            <th class="table-head w-36">Spend to reach ({{ $symbol }})</th>
                            <th class="table-head w-28">Points ×</th>
                            <th class="table-head w-44">Card design</th>
                            <th class="table-head">Perks line</th>
                            <th class="table-head w-20 text-right">Members</th>
                            <th class="table-head w-16"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(row, index) in rows" :key="index">
                            <tr x-bind:class="row.remove ? 'opacity-40' : ''">
                                <td class="py-2 pr-2">
                                    <input type="hidden" x-bind:name="`tiers[${index}][id]`" x-bind:value="row.id ?? ''">
                                    <input type="text" class="form-input" required maxlength="40"
                                        x-bind:name="`tiers[${index}][name]`" x-model="row.name" placeholder="e.g. Gold">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="number" step="0.01" min="0" class="form-input text-right" required
                                        x-bind:name="`tiers[${index}][min_spend]`" x-model="row.min_spend">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="number" step="0.01" min="0" max="10" class="form-input text-right" required
                                        x-bind:name="`tiers[${index}][earn_multiplier]`" x-model="row.earn_multiplier">
                                </td>
                                <td class="px-2 py-2">
                                    <select class="form-input pr-8" x-bind:name="`tiers[${index}][card_theme]`" x-model="row.card_theme">
                                        @foreach ($themes as $theme)
                                            <option value="{{ $theme->value }}">{{ $theme->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    <input type="text" class="form-input" maxlength="255"
                                        x-bind:name="`tiers[${index}][perks]`" x-model="row.perks" placeholder="Shown to members">
                                </td>
                                <td class="px-2 py-2 text-right text-sm text-slate-600" x-text="row.members"></td>
                                <td class="py-2 pl-2 text-right">
                                    <template x-if="row.id">
                                        <label class="inline-flex cursor-pointer items-center gap-1 text-xs text-slate-500" title="Remove this tier on save">
                                            <input type="checkbox" value="1" class="rounded border-slate-300 text-red-600"
                                                x-bind:name="`tiers[${index}][remove]`" x-model="row.remove">
                                            Remove
                                        </label>
                                    </template>
                                    <template x-if="!row.id">
                                        <button type="button" x-on:click="rows.splice(index, 1)"
                                            class="rounded p-1 text-slate-300 hover:bg-red-50 hover:text-red-600">✕</button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <button type="button" x-on:click="addTier()" class="mt-3 text-sm font-medium text-brand-700 hover:underline">+ Add a tier</button>
            <p class="form-hint">Members of a removed tier are moved to the tier their spend earns when you save.</p>
        </x-form-section>

        <x-form-section title="Back of the card" icon="▭">
            <x-field label="Small print" name="card_terms" hint="Leave empty to use the standard wording.">
                <x-textarea name="card_terms" id="card_terms" rows="3" :placeholder="$settings->cardTerms()">{{ old('card_terms', $settings->card_terms) }}</x-textarea>
            </x-field>
        </x-form-section>

        <div class="flex gap-2">
            <x-button type="submit">Save settings</x-button>
            <x-button :href="route('customers.index')" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-layouts.app>
