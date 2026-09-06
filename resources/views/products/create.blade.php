<x-layouts.app title="New product">
    <x-page-header title="Add a product"
        description="Set the item up once — packet or loose — and it is ready to receive stock."
        :back="route('products.index')" />

    @include('products._form')
</x-layouts.app>
