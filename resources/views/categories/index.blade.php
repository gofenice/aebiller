<x-layouts.app title="Categories">
    <x-page-header title="Categories" description="Group products the way the store is laid out.">
        <x-slot:actions>
            <x-button :href="route('categories.create')">+ New category</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="GET" class="flex gap-3 border-b border-slate-200 p-4">
            <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Search categories…" class="max-w-xs" />
            <x-button type="submit" variant="secondary">Search</x-button>
        </form>

        @if ($categories->isEmpty())
            <x-empty-state icon="⌗" title="No categories yet"
                description="Add categories like Fruits & Vegetables, Dairy, Staples or Household.">
                <x-slot:actions><x-button :href="route('categories.create')">+ New category</x-button></x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Category</th>
                            <th class="table-head">Parent</th>
                            <th class="table-head text-right">Products</th>
                            <th class="table-head">Status</th>
                            <th class="table-head text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($categories as $category)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <span class="font-medium text-slate-900">{{ $category->name }}</span>
                                    @if ($category->description)
                                        <span class="block text-xs text-slate-400">{{ $category->description }}</span>
                                    @endif
                                </td>
                                <td class="table-cell text-slate-600">{{ $category->parent?->name ?? '—' }}</td>
                                <td class="table-cell text-right text-slate-600">
                                    <a href="{{ route('products.index', ['category' => $category->id]) }}" class="hover:text-brand-700">{{ $category->products_count }}</a>
                                </td>
                                <td class="table-cell">
                                    <x-badge :color="$category->is_active ? 'green' : 'slate'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                </td>
                                <td class="table-cell text-right">
                                    <div class="flex justify-end gap-1">
                                        <x-button :href="route('categories.edit', $category)" variant="ghost" size="sm">Edit</x-button>
                                        @can('delete-records')
                                            <form method="POST" action="{{ route('categories.destroy', $category) }}"
                                                onsubmit="return confirm('Delete {{ addslashes($category->name) }}?')">
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
            <div class="border-t border-slate-200 px-4 py-3">{{ $categories->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
