<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100">
<div class="flex min-h-full items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex flex-col items-center text-center">
            <x-logo size="lg" class="mb-3" />
            <h1 class="text-lg font-semibold text-slate-900">{{ config('app.name') }}</h1>
            <p class="text-sm text-slate-500">Staff sign in</p>
        </div>

        <div class="card p-6">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <x-field label="Email address" name="email" required>
                    <x-input type="email" name="email" id="email" value="{{ old('email') }}"
                        required autofocus autocomplete="username" placeholder="you@store.com" />
                </x-field>

                <x-field label="Password" name="password" required>
                    <x-input type="password" name="password" id="password"
                        required autocomplete="current-password" placeholder="••••••••" />
                </x-field>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1"
                        class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Keep me signed in
                </label>

                <x-button type="submit" class="w-full">Sign in</x-button>
            </form>
        </div>

        <p class="mt-4 text-center text-xs text-slate-400">
            Accounts are created by a super admin.
        </p>
    </div>
</div>
</body>
</html>
