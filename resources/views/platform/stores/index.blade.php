<x-layouts.platform title="Stores">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">Stores</h1>
            <p class="mt-1 text-sm text-slate-500">Every shop paying for the system.</p>
        </div>
        <x-button :href="route('platform.stores.create')">+ New store</x-button>
    </div>

    <x-card>
        <form method="GET" class="flex flex-wrap gap-3 border-b border-slate-200 p-4">
            <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Name, address or owner email…" class="max-w-xs" />
            <x-select name="status" class="w-44">
                <option value="">Any status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
                <option value="archived" @selected(request('status') === 'archived')>Archived ({{ $archivedCount }})</option>
            </x-select>
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>

        @if ($stores->isEmpty())
            <x-empty-state icon="◧" title="No stores found">
                <x-slot:actions><x-button :href="route('platform.stores.create')">+ New store</x-button></x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Store</th>
                            <th class="table-head">Owner</th>
                            <th class="table-head">Currency</th>
                            <th class="table-head text-right">Staff</th>
                            <th class="table-head text-right">Products</th>
                            <th class="table-head text-right">Bills this month</th>
                            <th class="table-head">Status</th>
                            <th class="table-head text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($stores as $store)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <a href="{{ route('platform.stores.show', $store) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $store->name }}</a>
                                    <span class="block font-mono text-xs text-slate-400">{{ $store->host() }}</span>
                                </td>
                                <td class="table-cell text-slate-600">
                                    {{ $store->owner_name ?? '—' }}
                                    @if ($store->owner_email)
                                        <span class="block text-xs text-slate-400">{{ $store->owner_email }}</span>
                                    @endif
                                </td>
                                <td class="table-cell text-slate-600">{{ $store->currency_code }}</td>
                                <td class="table-cell text-right text-slate-600">{{ $store->users_count }}</td>
                                <td class="table-cell text-right text-slate-600">{{ $store->products_count }}</td>
                                <td class="table-cell text-right font-semibold text-slate-900">{{ $store->bills_this_month }}</td>
                                <td class="table-cell">
                                    @if ($store->isArchived())
                                        <x-badge color="slate">Archived</x-badge>
                                        <span class="block text-xs text-slate-400">
                                            kept until {{ $store->purgeableOn()?->format('d M Y') }}
                                        </span>
                                    @else
                                        <x-badge :color="$store->status->color()">{{ $store->status->label() }}</x-badge>
                                    @endif
                                </td>
                                <td class="table-cell text-right">
                                    <div class="flex justify-end gap-1">
                                        @if ($store->isArchived())
                                            <form method="POST" action="{{ route('platform.stores.restore', $store->id) }}">
                                                @csrf
                                                <x-button type="submit" variant="ghost" size="sm">Restore</x-button>
                                            </form>

                                            <div x-data="{ open: false }" class="inline-block">
                                                <x-button type="button" variant="ghost" size="sm" class="text-red-600"
                                                    x-on:click="open = true">Delete</x-button>

                                                <div x-show="open" x-cloak
                                                    class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4"
                                                    x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                                                    <form method="POST" action="{{ route('platform.stores.purge', $store->id) }}"
                                                        class="w-full max-w-md space-y-3 rounded-xl bg-white p-5 text-left shadow-xl">
                                                        @csrf
                                                        @method('DELETE')
                                                        <h3 class="text-sm font-semibold text-slate-900">Delete {{ $store->name }} for good?</h3>
                                                        <p class="text-xs text-slate-600">
                                                            Every product, bill, customer, staff login, invoice and payment of this
                                                            store goes with it. This cannot be undone.
                                                        </p>
                                                        <x-field label="Type {{ $store->original_slug ?: $store->slug }} to confirm" name="confirm">
                                                            <x-input name="confirm" class="font-mono" autocomplete="off" required />
                                                        </x-field>
                                                        <div class="flex justify-end gap-2">
                                                            <x-button type="button" variant="secondary" x-on:click="open = false">Cancel</x-button>
                                                            <x-button type="submit" variant="danger">Delete permanently</x-button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @else
                                            <x-button :href="route('platform.stores.show', $store)" variant="ghost" size="sm">View</x-button>
                                            <x-button :href="$store->url()" target="_blank" variant="ghost" size="sm">Open ↗</x-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $stores->links() }}</div>
        @endif
    </x-card>
</x-layouts.platform>
