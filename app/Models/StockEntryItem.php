<?php

namespace App\Models;

use Database\Factories\StockEntryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_entry_id', 'product_id', 'stock_batch_id', 'batch_number', 'manufactured_on', 'expires_on',
    'quantity', 'free_quantity', 'unit_cost', 'discount_percent', 'tax_percent', 'tax_amount',
    'line_total', 'selling_price',
])]
class StockEntryItem extends Model
{
    /** @use HasFactory<StockEntryItemFactory> */
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
            'quantity' => 'decimal:3',
            'free_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StockEntry, $this>
     */
    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<StockBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    /**
     * Quantity that actually lands in stock, including free/bonus units.
     */
    public function totalQuantity(): float
    {
        return (float) $this->quantity + (float) $this->free_quantity;
    }
}
