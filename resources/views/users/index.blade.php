<x-layouts.app title="Staff accounts">
    <x-page-header title="Staff accounts"
        description="Super admins manage everything; admins run day-to-day inventory work.">
        <x-slot:actions>
            <x-button :href="route('users.create')">+ New account</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-3 sm:grid-cols-2">
        <div class="card p-4">
            <p class="text-sm font-semibold text-slate-900">Super admin</p>
            <p class="mt-1 text-xs leading-relaxed text-slate-500">
                Everything an admin can do, plus staff accounts, deleting products, categories, suppliers and
                reversing posted stock entries.
            </p>
        </div>
        <div class="card p-4">
            <p class="text-sm font-semibold text-slate-900">Admin</p>
            <p class="mt-1 text-xs leading-relaxed text-slate-500">
                Add and edit products, receive stock, post adjustments and read every report. Cannot delete records
                or manage staff accounts.
            </p>
        </div>
    </div>

    <x-card>
        <form method="GET" class="flex flex-wrap gap-3 border-b border-slate-200 p-4">
            <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Name or email…" class="max-w-xs" />
            <x-select name="role" class="w-48">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </x-select>
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="table-head">Name</th>
                        <th class="table-head">Email</th>
                        <th class="table-head">Role</th>
                        <th class="table-head">Last signed in</th>
                        <th class="table-head">Status</th>
                        <th class="table-head text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        <tr class="hover:bg-slate-50/70">
                            <td class="table-cell">
                                <span class="font-medium text-slate-900">{{ $user->name }}</span>
                                @if ($user->is(auth()->user()))
                                    <span class="ml-1 text-xs text-slate-400">(you)</span>
                                @endif
                                @if ($user->phone)
                                    <span class="block text-xs text-slate-400">{{ $user->phone }}</span>
                                @endif
                            </td>
                            <td class="table-cell text-slate-600">{{ $user->email }}</td>
                            <td class="table-cell">
                                <x-badge :color="$user->isSuperAdmin() ? 'violet' : 'blue'">{{ $user->role->label() }}</x-badge>
                            </td>
                            <td class="table-cell text-slate-500">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="table-cell">
                                <x-badge :color="$user->is_active ? 'green' : 'slate'">{{ $user->is_active ? 'Active' : 'Disabled' }}</x-badge>
                            </td>
                            <td class="table-cell text-right">
                                <div class="flex justify-end gap-1">
                                    <x-button :href="route('users.edit', $user)" variant="ghost" size="sm">Edit</x-button>
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                                            onsubmit="return confirm('Remove {{ addslashes($user->name) }}?')">
                                            @csrf @method('DELETE')
                                            <x-button type="submit" variant="ghost" size="sm" class="text-red-600 hover:bg-red-50">Delete</x-button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $users->links() }}</div>
    </x-card>
</x-layouts.app>
