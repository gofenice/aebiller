<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\StoreInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One month of subscription billed to a store. Platform-level: it is the
 * platform's record of what a shop owes, not the shop's own data.
 */
#[Fillable([
    'store_id', 'plan_id', 'number', 'period_start', 'period_end', 'amount', 'amount_paid',
    'currency_code', 'status', 'issued_on', 'due_on', 'paid_at', 'notes',
    'is_prorated', 'overdue_notified_at',
    'razorpay_payment_link_id', 'razorpay_short_url', 'razorpay_payment_id',
])]
class StoreInvoice extends Model
{
    /** @use HasFactory<StoreInvoiceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
            'paid_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'is_prorated' => 'boolean',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<StorePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(StorePayment::class);
    }

    public function outstanding(): float
    {
        return round(max((float) $this->amount - (float) $this->amount_paid, 0), 2);
    }

    public function isSettled(): bool
    {
        return in_array($this->status, [InvoiceStatus::Paid, InvoiceStatus::Void], true);
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Overdue;
    }

    /**
     * The day the store is closed if this invoice is still unpaid.
     */
    public function suspendOn(): Carbon
    {
        return $this->due_on->copy()->addDays($this->store?->grace_days ?? 7);
    }

    /**
     * "Sep 2026", "Sep 2026 – Aug 2027", or "Lifetime".
     */
    public function periodLabel(): string
    {
        if ($this->period_end === null) {
            return 'Lifetime';
        }

        return $this->period_end->diffInDays($this->period_start) > 40
            ? $this->period_start->format('M Y').' – '.$this->period_end->format('M Y')
            : $this->period_start->format('M Y');
    }

    /**
     * The shop is being told early rather than chased. Driven by the status
     * the nightly review sets, so screens and the job never disagree.
     */
    public function isDueSoon(): bool
    {
        return ! $this->isSettled() && $this->status !== InvoiceStatus::Overdue;
    }

    /**
     * @param  Builder<StoreInvoice>  $query
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->whereIn('status', InvoiceStatus::unpaid());
    }

    /**
     * @param  Builder<StoreInvoice>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Overdue);
    }
}
