<x-layouts.plain :title="config('tenancy.platform_name').' — sign in'">
    <div class="mb-6 flex flex-col items-center text-center">
        <span class="mb-3 flex size-12 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white">PG</span>
        <h1 class="text-lg font-semibold tracking-tight text-slate-900">{{ config('tenancy.platform_name') }}</h1>
        <p class="text-sm text-slate-500">Platform sign-in</p>
    </div>

    <x-alerts />

    <form method="POST" action="{{ route('platform.login') }}" class="space-y-4">
        @csrf

        <x-field label="Email" name="email" required>
            <x-input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
        </x-field>

        <x-field label="Password" name="password" required>
            <x-input type="password" name="password" id="password" required autocomplete="current-password" />
        </x-field>

        <x-checkbox name="remember" label="Stay signed in" :checked="old('remember')" />

        <x-button type="submit" size="lg" class="w-full">Sign in</x-button>
    </form>

    <p class="mt-6 text-center text-xs text-slate-400">
        Shop staff sign in on their own store's address, not here.
    </p>
</x-layouts.plain>
