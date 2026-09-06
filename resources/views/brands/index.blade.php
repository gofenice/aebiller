<x-layouts.app title="Brands">
    <x-page-header title="Brands" description="Manufacturers and labels carried by the store." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card>
                <form method="GET" class="flex gap-3 border-b border-slate-200 p-4">
                    <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Search brands…" class="max-w-xs" />
                    <x-button type="submit" variant="secondary">Search</x-button>
                </form>

                @if ($brands->isEmpty())
                    <x-empty-state icon="◈" title="No brands yet" description="Add the first brand on the right." />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head">Brand</th>
                                    <th class="table-head text-right">Products</th>
                                    <th class="table-head">Status</th>
                                    <th class="table-head text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($brands as $brand)
                                    <tr>
                                        <td class="table-cell">
                                            <form method="POST" action="{{ route('brands.update', $brand) }}" class="flex items-center gap-2">
                                                @csrf @method('PUT')
                                                <input type="text" name="name" value="{{ $brand->name }}"
                                                    class="form-input max-w-56 py-1.5 text-sm">
                                                <input type="hidden" name="is_active" value="{{ $brand->is_active ? 1 : 0 }}">
                                                <x-button type="submit" variant="ghost" size="sm">Save</x-button>
                                            </form>
                                        </td>
                                        <td class="table-cell text-right text-slate-600">
                                            <a href="{{ route('products.index', ['brand' => $brand->id]) }}" class="hover:text-brand-700">{{ $brand->products_count }}</a>
                                        </td>
                                        <td class="table-cell">
                                            <x-badge :color="$brand->is_active ? 'green' : 'slate'">{{ $brand->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                        </td>
                                        <td class="table-cell text-right">
                                            @can('delete-records')
                                                <form method="POST" action="{{ route('brands.destroy', $brand) }}"
                                                    onsubmit="return confirm('Delete {{ addslashes($brand->name) }}?')">
                                                    @csrf @method('DELETE')
                                                    <x-button type="submit" variant="ghost" size="sm" class="text-red-600 hover:bg-red-50">Delete</x-button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-200 px-4 py-3">{{ $brands->links() }}</div>
                @endif
            </x-card>
        </div>

        <x-form-section title="Add a brand" icon="◈">
            <form method="POST" action="{{ route('brands.store') }}" class="space-y-4">
                @csrf
                <x-field label="Brand name" name="name" required>
                    <x-input name="name" id="name" value="{{ old('name') }}" required placeholder="e.g. Amul" />
                </x-field>
                <x-button type="submit" class="w-full">Add brand</x-button>
            </form>
        </x-form-section>
    </div>
</x-layouts.app>
