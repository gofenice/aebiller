<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\StockEntry;
use App\Models\Supplier;
use App\Services\ReferenceNumbers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(protected ReferenceNumbers $references) {}

    /**
     * Money going out: supplier bills, staff tea, travel, everything.
     */
    public function index(Request $request): View
    {
        $this->authorize('manage-expenses');

        $showingUnpaid = $request->string('status')->toString() === 'unpaid';

        // The list defaults to this month, but money still owed is never hidden
        // by that window — an unpaid bill from March is still unpaid today.
        $from = $request->string('from')->toString()
            ?: ($showingUnpaid ? null : now()->startOfMonth()->toDateString());
        $to = $request->string('to')->toString()
            ?: ($showingUnpaid ? null : now()->toDateString());

        $filtered = Expense::query()
            ->between($from, $to)
            ->when($request->filled('category'), fn ($q) => $q->where('expense_category_id', $request->integer('category')))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->string('payment_method')->toString()))
            ->when($request->string('status')->toString() === 'unpaid', fn ($q) => $q->unpaid())
            ->when($request->string('status')->toString() === 'paid', fn ($q) => $q->where('is_paid', true))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = $request->string('search')->toString();
                $query->where(function ($query) use ($term): void {
                    $query->where('description', 'like', "%{$term}%")
                        ->orWhere('reference_no', 'like', "%{$term}%")
                        ->orWhere('invoice_number', 'like', "%{$term}%")
                        ->orWhere('payee', 'like', "%{$term}%");
                });
            });

        $summary = (clone $filtered)
            ->select(
                DB::raw('count(*) as entries'),
                DB::raw('coalesce(sum(total), 0) as total'),
                DB::raw('coalesce(sum(vat_amount), 0) as vat'),
                DB::raw('coalesce(sum(case when is_paid = 0 then total else 0 end), 0) as outstanding'),
            )
            ->first();

        return view('expenses.index', [
            'expenses' => (clone $filtered)
                ->with(['category', 'supplier', 'creator'])
                ->latest('expense_date')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'summary' => $summary,
            'byCategory' => (clone $filtered)
                ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->select('expense_categories.name', DB::raw('count(*) as entries'), DB::raw('sum(expenses.total) as total'))
                ->groupBy('expense_categories.id', 'expense_categories.name')
                ->orderByDesc('total')
                ->get(),
            'categories' => ExpenseCategory::active()->orderBy('sort_order')->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::cases(),
            'from' => $from,
            'to' => $to,
            'showingUnpaid' => $showingUnpaid,
            // Outstanding is always measured across all time, not the filter.
            'outstandingTotal' => (float) Expense::unpaid()->sum('total'),
            'outstandingCount' => Expense::unpaid()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('manage-expenses');

        return view('expenses.form', [
            ...$this->formData(),
            'expense' => new Expense([
                'expense_date' => now()->toDateString(),
                'vat_rate' => 0,
                'is_paid' => true,
                'payment_method' => PaymentMethod::Cash,
                'stock_entry_id' => $request->integer('stock_entry') ?: null,
            ]),
            'nextReference' => $this->references->next(Expense::class, 'EXP'),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = Expense::create([
            ...$this->attributes($request),
            'reference_no' => $this->references->next(Expense::class, 'EXP'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('expenses.index')
            ->with('status', "Expense {$expense->reference_no} recorded.");
    }

    public function show(Expense $expense): View
    {
        $this->authorize('manage-expenses');

        $expense->load(['category', 'supplier', 'stockEntry', 'creator']);

        return view('expenses.show', ['expense' => $expense]);
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('manage-expenses');

        return view('expenses.form', [
            ...$this->formData(),
            'expense' => $expense,
            'nextReference' => $expense->reference_no,
        ]);
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->update($this->attributes($request, $expense));

        return redirect()
            ->route('expenses.show', $expense)
            ->with('status', "Expense {$expense->reference_no} updated.");
    }

    /**
     * Super admins only — an expense is a financial record.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete-records');

        if ($expense->attachment_path) {
            Storage::disk('public')->delete($expense->attachment_path);
        }

        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with('status', "Expense {$expense->reference_no} was deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(): array
    {
        return [
            'categories' => ExpenseCategory::active()->orderBy('sort_order')->orderBy('name')->get(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::cases(),
            'taxRates' => config('inventory.tax_rates'),
            'recentEntries' => StockEntry::with('supplier')->latest('entry_date')->latest('id')->limit(25)->get(),
        ];
    }

    /**
     * Work the VAT out server-side so the stored figures always add up.
     *
     * @return array<string, mixed>
     */
    protected function attributes(StoreExpenseRequest $request, ?Expense $expense = null): array
    {
        $data = $request->safe()->except('attachment');

        $amount = round((float) $data['amount'], 2);
        $vatRate = (float) $data['vat_rate'];
        $vatAmount = round($amount * $vatRate / 100, 2);

        $data['amount'] = $amount;
        $data['vat_amount'] = $vatAmount;
        $data['total'] = round($amount + $vatAmount, 2);
        $data['paid_on'] = $data['is_paid'] ? ($data['paid_on'] ?? $data['expense_date']) : null;

        if ($request->hasFile('attachment')) {
            if ($expense?->attachment_path) {
                Storage::disk('public')->delete($expense->attachment_path);
            }

            $data['attachment_path'] = $request->file('attachment')->store('expenses', 'public');
        }

        return $data;
    }
}
