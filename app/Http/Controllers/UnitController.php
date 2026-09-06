<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        return view('units.index', [
            'units' => Unit::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-masters');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:units,name'],
            'code' => ['required', 'string', 'max:12', 'unique:units,code'],
            'allows_decimal' => ['boolean'],
        ]);

        Unit::create([...$data, 'allows_decimal' => $request->boolean('allows_decimal'), 'is_active' => true]);

        return back()->with('status', "Unit \"{$data['name']}\" was added.");
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('manage-masters');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:units,name,'.$unit->id],
            'code' => ['required', 'string', 'max:12', 'unique:units,code,'.$unit->id],
            'allows_decimal' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $unit->update([
            ...$data,
            'allows_decimal' => $request->boolean('allows_decimal'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', "Unit \"{$unit->name}\" was updated.");
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete-records');

        if ($unit->products()->exists()) {
            return back()->with('error', "\"{$unit->name}\" is in use by one or more products.");
        }

        $unit->delete();

        return back()->with('status', "Unit \"{$unit->name}\" was deleted.");
    }
}
