<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        return view('brands.index', [
            'brands' => Brand::query()
                ->withCount('products')
                ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-masters');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:brands,name'],
        ]);

        Brand::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'is_active' => true,
        ]);

        return back()->with('status', "Brand \"{$data['name']}\" was added.");
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorize('manage-masters');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:brands,name,'.$brand->id],
            'is_active' => ['boolean'],
        ]);

        $brand->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', "Brand \"{$brand->name}\" was updated.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('delete-records');

        if ($brand->products()->exists()) {
            return back()->with('error', "\"{$brand->name}\" still has products linked to it.");
        }

        $brand->delete();

        return back()->with('status', "Brand \"{$brand->name}\" was deleted.");
    }
}
