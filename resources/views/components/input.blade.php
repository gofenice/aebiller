{{-- `name` is read from the attribute bag rather than declared as a prop, so it still renders on the input. --}}
@php
    $field = $attributes->get('name');
    $hasError = $field && $errors->has($field);
@endphp

<input {{ $attributes->merge([
    'type' => 'text',
    'class' => 'form-input'.($hasError ? ' border-red-400 focus:border-red-500 focus:ring-red-500/20' : ''),
]) }}>
