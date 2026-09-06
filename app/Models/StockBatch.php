<?php

namespace App\Models;

use Database\Factories\StockBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'supplier_id', 'batch_number', 'manufactured_on', 'expires_on',
    'received_quantity', 'quantity', 'cost_price', 'received_on',
])]
class StockBatch extends Model
{
    /** @use HasFactory<StockBatchFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manufactured_on' => 'date',
            'expires_on' => 'date',
            'received_on' => 'date',
            'received_quantity' => 'decimal:3',
            'quantity' => 'decimal:3',
            'cost_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    public function daysToExpiry(): ?int
    {
        return $this->expires_on?->startOfDay()->diffInDays(now()->startOfDay(), false) * -1;
    }

    /**
     * @param  Builder<StockBatch>  $query
     */
    public function scopeInStock(Builder $query): void
    {
        $query->where('quantity', '>', 0);
    }

    /**
     * @param  Builder<StockBatch>  $query
     */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->whereNotNull('expires_on')
            ->whereDate('expires_on', '>=', now()->toDateString())
            ->whereDate('expires_on', '<=', now()->addDays($days)->toDateString());
    }

    /**
     * @param  Builder<StockBatch>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_on')->whereDate('expires_on', '<', now()->toDateString());
    }
}
