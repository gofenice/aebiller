<x-layouts.plain title="Store not found">
    <h1 class="text-xl font-semibold tracking-tight text-slate-900">No store at this address</h1>
    <p class="mt-2 text-sm text-slate-600">
        <span class="font-mono text-slate-800">{{ $host }}</span> does not belong to any store.
        Check the spelling of the address, or ask the shop for the right link.
    </p>
    <p class="mt-4 text-xs text-slate-400">{{ config('tenancy.platform_name') }}</p>
</x-layouts.plain>
