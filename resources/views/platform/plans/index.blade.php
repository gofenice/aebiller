<x-layouts.platform title="Plans">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">Plans</h1>
            <p class="mt-1 text-sm text-slate-500">What stores pay each month. Changing a price affects future invoices only.</p>
        </div>
        <x-button :href="route('platform.plans.create')">+ New plan</x-button>
    </div>

    <x-card>
        @if ($plans->isEmpty())
            <x-empty-state icon="¤" title="No plans yet"
                description="Add a plan so a store can be put on a monthly price.">
                <x-slot:actions><x-button :href="route('platform.plans.create')">+ New plan</x-button></x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Plan</th>
                            <th class="table-head">Charged</th>
                            <th class="table-head text-right">Price</th>
                            <th class="table-head text-right">Stores</th>
                            <th class="table-head">Status</th>
                            <th class="table-head text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($plans as $plan)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <span class="font-medium text-slate-900">{{ $plan->name }}</span>
                                    @if ($plan->description)
                                        <span class="block text-xs text-slate-400">{{ $plan->description }}</span>
                                    @endif
                                </td>
                                <td class="table-cell">
                                    <x-badge :color="$plan->billing_period === \App\Enums\BillingPeriod::Lifetime ? 'violet' : 'blue'">
                                        {{ $plan->billing_period->label() }}
                                    </x-badge>
                                </td>
                                <td class="table-cell text-right font-semibold text-slate-900">
                                    {{ $plan->currency_code }} {{ number_format((float) $plan->monthly_price, 2) }}
                                    <span class="block text-xs font-normal text-slate-400">
                                        {{ $plan->billing_period->suffix() }}
                                        @if ($plan->prices->isNotEmpty())
                                            · {{ $plan->prices->pluck('currency_code')->implode(', ') }}
                                        @endif
                                    </span>
                                </td>
                                <td class="table-cell text-right text-slate-600">{{ $plan->stores_count }}</td>
                                <td class="table-cell">
                                    <x-badge :color="$plan->is_active ? 'green' : 'slate'">{{ $plan->is_active ? 'Active' : 'Retired' }}</x-badge>
                                </td>
                                <td class="table-cell text-right">
                                    <div class="flex justify-end gap-1">
                                        <x-button :href="route('platform.plans.edit', $plan)" variant="ghost" size="sm">Edit</x-button>
                                        @if ($plan->stores_count === 0)
                                            <form method="POST" action="{{ route('platform.plans.destroy', $plan) }}"
                                                onsubmit="return confirm('Delete the {{ addslashes($plan->name) }} plan?')">
                                                @csrf @method('DELETE')
                                                <x-button type="submit" variant="ghost" size="sm" class="text-red-600 hover:bg-red-50">Delete</x-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.platform>
