@props(['title' => 'Nothing here yet', 'description' => null, 'icon' => '📦'])

<div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <div class="mb-3 flex size-12 items-center justify-center rounded-full bg-slate-100 text-xl">{{ $icon }}</div>
    <p class="text-sm font-semibold text-slate-800">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-4 flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
