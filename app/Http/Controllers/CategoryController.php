<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('categories.index', [
            'categories' => Category::query()
                ->with('parent')
                ->withCount('products')
                ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'parents' => Category::whereNull('parent_id')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('categories.form', [
            'category' => new Category(['is_active' => true]),
            'parents' => Category::whereNull('parent_id')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-masters');

        $data = $this->validated($request);
        Category::create($data);

        return redirect()->route('categories.index')->with('status', "Category \"{$data['name']}\" was created.");
    }

    public function edit(Category $category): View
    {
        return view('categories.form', [
            'category' => $category,
            'parents' => Category::whereNull('parent_id')->where('id', '!=', $category->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('manage-masters');

        $category->update($this->validated($request, $category));

        return redirect()->route('categories.index')->with('status', "Category \"{$category->name}\" was updated.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete-records');

        if ($category->products()->exists()) {
            return back()->with('error', "\"{$category->name}\" still has products linked to it.");
        }

        $category->children()->update(['parent_id' => null]);
        $category->delete();

        return back()->with('status', "Category \"{$category->name}\" was deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] ??= 0;
        $data['slug'] = $this->uniqueSlug($data['name'], $category);

        return $data;
    }

    protected function uniqueSlug(string $name, ?Category $category): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Category::where('slug', $slug)->when($category, fn ($query) => $query->where('id', '!=', $category->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
