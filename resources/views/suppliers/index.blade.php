<x-layouts.app title="Suppliers">
    <x-page-header title="Suppliers" description="Distributors and farmers you buy from.">
        <x-slot:actions>
            <x-button :href="route('suppliers.create')">+ New supplier</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="GET" class="flex gap-3 border-b border-slate-200 p-4">
            <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Name, code or phone…" class="max-w-xs" />
            <x-button type="submit" variant="secondary">Search</x-button>
        </form>

        @if ($suppliers->isEmpty())
            <x-empty-state icon="◎" title="No suppliers yet"
                description="Add a supplier so goods receipts can be recorded against them.">
                <x-slot:actions><x-button :href="route('suppliers.create')">+ New supplier</x-button></x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Supplier</th>
                            <th class="table-head">Contact</th>
                            <th class="table-head">VAT number</th>
                            <th class="table-head text-right">Terms</th>
                            <th class="table-head text-right">Products</th>
                            <th class="table-head">Status</th>
                            <th class="table-head text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($suppliers as $supplier)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <span class="font-medium text-slate-900">{{ $supplier->name }}</span>
                                    <span class="block font-mono text-xs text-slate-400">{{ $supplier->code }}</span>
                                </td>
                                <td class="table-cell text-slate-600">
                                    {{ $supplier->contact_person ?? '—' }}
                                    @if ($supplier->phone)
                                        <span class="block text-xs text-slate-400">{{ $supplier->phone }}</span>
                                    @endif
                                </td>
                                <td class="table-cell font-mono text-xs text-slate-500">{{ $supplier->vat_number ?? '—' }}</td>
                                <td class="table-cell text-right text-slate-600">{{ $supplier->payment_terms_days }}d</td>
                                <td class="table-cell text-right text-slate-600">{{ $supplier->products_count }}</td>
                                <td class="table-cell">
                                    <x-badge :color="$supplier->is_active ? 'green' : 'slate'">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                </td>
                                <td class="table-cell text-right">
                                    <div class="flex justify-end gap-1">
                                        <x-button :href="route('suppliers.edit', $supplier)" variant="ghost" size="sm">Edit</x-button>
                                        @can('delete-records')
                                            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}"
                                                onsubmit="return confirm('Delete {{ addslashes($supplier->name) }}?')">
                                                @csrf @method('DELETE')
                                                <x-button type="submit" variant="ghost" size="sm" class="text-red-600 hover:bg-red-50">Delete</x-button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $suppliers->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
