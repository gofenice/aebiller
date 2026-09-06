@props(['product'])

@php
    $status = $product->stockStatus();
    $map = [
        'in_stock' => ['green', 'In stock'],
        'low_stock' => ['amber', 'Low stock'],
        'out_of_stock' => ['red', 'Out of stock'],
    ];
    [$color, $label] = $map[$status];
@endphp

<x-badge :color="$color">{{ $label }}</x-badge>
