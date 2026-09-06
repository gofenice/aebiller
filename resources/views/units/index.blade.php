<x-layouts.app title="Units">
    <x-page-header title="Units of measure"
        description="Pieces and packs for packet goods; kilograms, grams and litres for loose produce." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="All units">
                @if ($units->isEmpty())
                    <x-empty-state icon="⚖" title="No units yet" description="Add the first unit on the right." />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head">Unit</th>
                                    <th class="table-head">Code</th>
                                    <th class="table-head">Decimals</th>
                                    <th class="table-head text-right">Products</th>
                                    <th class="table-head">Status</th>
                                    <th class="table-head text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($units as $unit)
                                    <tr>
                                        <td class="table-cell font-medium text-slate-900">{{ $unit->name }}</td>
                                        <td class="table-cell font-mono text-slate-600">{{ $unit->code }}</td>
                                        <td class="table-cell">
                                            <x-badge :color="$unit->allows_decimal ? 'violet' : 'slate'">
                                                {{ $unit->allows_decimal ? 'Fractional (0.250)' : 'Whole numbers' }}
                                            </x-badge>
                                        </td>
                                        <td class="table-cell text-right text-slate-600">{{ $unit->products_count }}</td>
                                        <td class="table-cell">
                                            <x-badge :color="$unit->is_active ? 'green' : 'slate'">{{ $unit->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                        </td>
                                        <td class="table-cell text-right">
                                            @can('delete-records')
                                                <form method="POST" action="{{ route('units.destroy', $unit) }}"
                                                    onsubmit="return confirm('Delete {{ addslashes($unit->name) }}?')">
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

        <x-form-section title="Add a unit" icon="⚖">
            <form method="POST" action="{{ route('units.store') }}" class="space-y-4">
                @csrf
                <x-field label="Name" name="name" required>
                    <x-input name="name" id="name" value="{{ old('name') }}" required placeholder="e.g. Kilogram" />
                </x-field>
                <x-field label="Short code" name="code" required>
                    <x-input name="code" id="code" value="{{ old('code') }}" required placeholder="e.g. kg" class="font-mono" />
                </x-field>
                <x-checkbox name="allows_decimal" label="Allows fractional quantities"
                    hint="Turn on for weight and volume units." :checked="old('allows_decimal', true)" />
                <x-button type="submit" class="w-full">Add unit</x-button>
            </form>
        </x-form-section>
    </div>
</x-layouts.app>
