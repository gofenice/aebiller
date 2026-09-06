<x-layouts.app :title="$category->exists ? 'Edit category' : 'New category'">
    <x-page-header :title="$category->exists ? 'Edit category' : 'New category'" :back="route('categories.index')" />

    <form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}"
        class="max-w-2xl">
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <x-form-section title="Category details" icon="⌗">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field class="sm:col-span-2" label="Name" name="name" required>
                    <x-input name="name" id="name" value="{{ old('name', $category->name) }}" required
                        placeholder="e.g. Fruits & Vegetables" />
                </x-field>

                <x-field label="Parent category" name="parent_id" hint="Leave blank for a top-level category.">
                    <x-select name="parent_id" id="parent_id">
                        <option value="">— None —</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) == $parent->id)>{{ $parent->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field label="Sort order" name="sort_order" hint="Lower numbers appear first.">
                    <x-input type="number" min="0" name="sort_order" id="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" />
                </x-field>

                <x-field class="sm:col-span-2" label="Description" name="description">
                    <x-input name="description" id="description" value="{{ old('description', $category->description) }}" placeholder="Optional" />
                </x-field>

                <div class="sm:col-span-2">
                    <x-checkbox name="is_active" label="Active" :checked="old('is_active', $category->is_active ?? true)" />
                </div>
            </div>

            <div class="mt-5 flex gap-2">
                <x-button type="submit">{{ $category->exists ? 'Save changes' : 'Create category' }}</x-button>
                <x-button :href="route('categories.index')" variant="secondary">Cancel</x-button>
            </div>
        </x-form-section>
    </form>
</x-layouts.app>
