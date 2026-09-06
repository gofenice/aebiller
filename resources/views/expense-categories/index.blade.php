<x-layouts.app title="Expense categories">
    <x-page-header title="Expense categories" description="How the shop's outgoings are grouped."
        :back="route('expenses.index')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="All categories">
                @if ($categories->isEmpty())
                    <x-empty-state icon="⌗" title="No categories yet" description="Add the first one on the right." />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head">Category</th>
                                    <th class="table-head text-right">Entries</th>
                                    <th class="table-head text-right">Total spent</th>
                                    <th class="table-head">Status</th>
                                    <th class="table-head text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($categories as $category)
                                    <tr>
                                        <td class="table-cell">
                                            <span class="font-medium text-slate-900">{{ $category->name }}</span>
                                            @if ($category->description)
                                                <span class="block text-xs text-slate-400">{{ $category->description }}</span>
                                            @endif
                                        </td>
                                        <td class="table-cell text-right text-slate-600">
                                            <a href="{{ route('expenses.index', ['category' => $category->id, 'from' => now()->subYear()->toDateString()]) }}"
                                                class="hover:text-brand-700">{{ $category->expenses_count }}</a>
                                        </td>
                                        <td class="table-cell text-right font-medium text-slate-800">@money($category->expenses_sum_total ?? 0)</td>
                                        <td class="table-cell">
                                            <x-badge :color="$category->is_active ? 'green' : 'slate'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                        </td>
                                        <td class="table-cell text-right">
                                            @can('delete-records')
                                                <form method="POST" action="{{ route('expense-categories.destroy', $category) }}"
                                                    onsubmit="return confirm('Delete {{ addslashes($category->name) }}?')">
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
                @endif
            </x-card>
        </div>

        <x-form-section title="Add a category" icon="⌗">
            <form method="POST" action="{{ route('expense-categories.store') }}" class="space-y-4">
                @csrf
                <x-field label="Name" name="name" required>
                    <x-input name="name" id="name" value="{{ old('name') }}" required placeholder="e.g. Vehicle fuel" />
                </x-field>
                <x-field label="Description" name="description">
                    <x-input name="description" id="description" value="{{ old('description') }}" placeholder="Optional" />
                </x-field>
                <x-button type="submit" class="w-full">Add category</x-button>
            </form>
        </x-form-section>
    </div>
</x-layouts.app>
