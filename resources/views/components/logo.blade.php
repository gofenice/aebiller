@props(['size' => 'sm'])

@php
    $initials = str(config('app.name'))
        ->explode(' ')
        ->take(3)
        ->map(fn (string $word): string => str($word)->substr(0, 1)->upper()->toString())
        ->implode('');

    $classes = $size === 'lg'
        ? 'size-12 rounded-xl text-base'
        : 'size-8 rounded-lg text-xs';
@endphp

<span {{ $attributes->merge([
    'class' => 'flex shrink-0 items-center justify-center bg-brand-600 font-bold tracking-tight text-white '.$classes,
]) }}>{{ $initials }}</span>
