@props(['label' => null, 'hint' => null, 'name'])

<label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 transition hover:border-slate-300 hover:bg-slate-50">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" id="{{ $name }}"
        {{ $attributes->merge(['class' => 'mt-0.5 size-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500']) }}>
    <span class="min-w-0">
        <span class="block text-sm font-medium text-slate-700">{{ $label }}</span>
        @if ($hint)
            <span class="block text-xs text-slate-500">{{ $hint }}</span>
        @endif
    </span>
</label>
