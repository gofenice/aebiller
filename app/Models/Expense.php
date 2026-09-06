<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference_no', 'expense_date', 'expense_category_id', 'supplier_id', 'payee', 'stock_entry_id',
    'description', 'invoice_number', 'amount', 'vat_rate', 'vat_amount', 'total',
    'payment_method', 'is_paid', 'paid_on', 'attachment_path', 'notes', 'created_by',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'paid_on' => 'date',
            'payment_method' => PaymentMethod::class,
            'is_paid' => 'boolean',
            'amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<StockEntry, $this>
     */
    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whoever the money went to, however they were recorded.
     */
    protected function paidTo(): Attribute
    {
        return Attribute::get(fn (): string => $this->supplier?->name ?? $this->payee ?? '—');
    }

    /**
     * @param  Builder<Expense>  $query
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('is_paid', false);
    }

    /**
     * @param  Builder<Expense>  $query
     */
    public function scopeBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query->when($from, fn (Builder $query) => $query->whereDate('expense_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('expense_date', '<=', $to));
    }
}
