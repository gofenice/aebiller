<?php

namespace App\Models;

use App\Enums\StockEntryType;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\StockEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'reference_no', 'type', 'entry_date', 'supplier_id', 'invoice_number', 'invoice_date',
    'subtotal', 'discount_total', 'tax_total', 'other_charges', 'grand_total', 'notes', 'created_by',
])]
class StockEntry extends Model
{
    /** @use HasFactory<StockEntryFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockEntryType::class,
            'entry_date' => 'date',
            'invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<StockEntryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockEntryItem::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }
}
