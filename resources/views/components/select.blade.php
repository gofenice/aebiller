@php
    $field = $attributes->get('name');
    $hasError = $field && $errors->has($field);
@endphp

<select {{ $attributes->merge([
    'class' => 'form-input pr-8'.($hasError ? ' border-red-400 focus:border-red-500 focus:ring-red-500/20' : ''),
]) }}>
    {{ $slot }}
</select>
