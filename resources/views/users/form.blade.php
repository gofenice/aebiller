<x-layouts.app :title="$user->exists ? 'Edit account' : 'New account'">
    <x-page-header :title="$user->exists ? 'Edit '.$user->name : 'New staff account'" :back="route('users.index')" />

    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="max-w-2xl">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <x-form-section title="Account details" icon="◍">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Full name" name="name" required>
                    <x-input name="name" id="name" value="{{ old('name', $user->name) }}" required />
                </x-field>

                <x-field label="Phone" name="phone">
                    <x-input name="phone" id="phone" value="{{ old('phone', $user->phone) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Email address" name="email" required>
                    <x-input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required />
                </x-field>

                <x-field class="sm:col-span-2" label="Role" name="role" required>
                    <x-select name="role" id="role" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field label="Password" name="password" :required="! $user->exists"
                    :hint="$user->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters.'">
                    <x-input type="password" name="password" id="password" autocomplete="new-password" />
                </x-field>

                <x-field label="Confirm password" name="password_confirmation">
                    <x-input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" />
                </x-field>

                <div class="sm:col-span-2">
                    <x-checkbox name="is_active" label="Account is active"
                        hint="Disabled accounts cannot sign in." :checked="old('is_active', $user->is_active ?? true)" />
                </div>
            </div>

            <div class="mt-5 flex gap-2">
                <x-button type="submit">{{ $user->exists ? 'Save changes' : 'Create account' }}</x-button>
                <x-button :href="route('users.index')" variant="secondary">Cancel</x-button>
            </div>
        </x-form-section>
    </form>
</x-layouts.app>
