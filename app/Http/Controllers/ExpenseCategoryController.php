<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage-expenses');

        return view('expense-categories.index', [
            'categories' => ExpenseCategory::withCount('expenses')
                ->withSum('expenses', 'total')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-expenses');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:expense_categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        ExpenseCategory::create([
            ...$data,
            'slug' => Str::slug($data['name']),
            'is_active' => true,
        ]);

        return back()->with('status', "Expense category \"{$data['name']}\" was added.");
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('manage-expenses');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:expense_categories,name,'.$expenseCategory->id],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $expenseCategory->update([
            ...$data,
            'slug' => Str::slug($data['name']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', "Expense category \"{$expenseCategory->name}\" was updated.");
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('delete-records');

        if ($expenseCategory->expenses()->exists()) {
            return back()->with('error', "\"{$expenseCategory->name}\" still has expenses booked against it.");
        }

        $expenseCategory->delete();

        return back()->with('status', "Expense category \"{$expenseCategory->name}\" was deleted.");
    }
}
