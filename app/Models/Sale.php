<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'uuid', 'invoice_no', 'status', 'customer_id', 'customer_name', 'customer_phone', 'customer_vat_number',
    'items_gross', 'line_discount_total', 'bill_discount', 'loyalty_discount', 'subtotal_excl_vat', 'vat_total',
    'grand_total', 'cost_total', 'payment_method', 'amount_paid', 'change_due', 'notes',
    'loyalty_points_earned', 'loyalty_points_redeemed', 'loyalty_balance_after',
    'cashier_id', 'sold_at', 'voided_by', 'voided_at', 'void_reason',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use BelongsToStore, HasFactory, HasUuids;

    /**
     * The bill is looked up publicly by its uuid, never by its id.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'payment_method' => PaymentMethod::class,
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
            'items_gross' => 'decimal:2',
            'line_discount_total' => 'decimal:2',
            'bill_discount' => 'decimal:2',
            'loyalty_discount' => 'decimal:2',
            'loyalty_points_earned' => 'integer',
            'loyalty_points_redeemed' => 'integer',
            'loyalty_balance_after' => 'integer',
            'subtotal_excl_vat' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'change_due' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * The loyalty member the bill was rung up for, if any.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function isVoided(): bool
    {
        return $this->status === SaleStatus::Voided;
    }

    /**
     * Gross profit on the bill, before overheads.
     */
    public function profit(): float
    {
        return round((float) $this->subtotal_excl_vat - (float) $this->cost_total, 2);
    }

    /**
     * The address printed into the receipt QR code.
     */
    public function publicUrl(): string
    {
        return route('bill.show', $this->uuid);
    }

    /**
     * @param  Builder<Sale>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', SaleStatus::Completed);
    }
}
