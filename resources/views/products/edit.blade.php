<x-layouts.app :title="'Edit '.$product->name">
    <x-page-header :title="'Edit '.$product->name"
        :description="$product->sku.($product->barcode ? ' · '.$product->barcode : '')"
        :back="route('products.show', $product)" />

    @include('products._form')
</x-layouts.app>
