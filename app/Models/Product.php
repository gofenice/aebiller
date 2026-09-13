<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\StorageType;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sku', 'barcode', 'name', 'short_name', 'type', 'category_id', 'brand_id', 'supplier_id',
    'unit_id', 'pack_size', 'pack_unit_id', 'units_per_case', 'hs_code', 'tax_rate',
    'price_includes_tax', 'cost_price', 'selling_price', 'opening_stock', 'current_stock',
    'reorder_level', 'max_stock_level', 'is_weighable', 'tare_weight', 'min_sale_quantity',
    'wastage_percent', 'track_batches', 'track_expiry', 'shelf_life_days', 'storage_type',
    'rack_location', 'image_path', 'description', 'is_active', 'created_by', 'updated_by',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToStore, HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'storage_type' => StorageType::class,
            'pack_size' => 'decimal:3',
            'tax_rate' => 'decimal:2',
            'price_includes_tax' => 'boolean',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'opening_stock' => 'decimal:3',
            'current_stock' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'max_stock_level' => 'decimal:3',
            'is_weighable' => 'boolean',
            'tare_weight' => 'decimal:3',
            'min_sale_quantity' => 'decimal:3',
            'wastage_percent' => 'decimal:2',
            'track_batches' => 'boolean',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function packUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'pack_unit_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<StockBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLoose(): bool
    {
        return $this->type === ProductType::Loose;
    }

    /**
     * Human readable pack description, e.g. "500 g" or "1 kg".
     */
    protected function packLabel(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->pack_size === null) {
                return null;
            }

            $size = rtrim(rtrim(number_format((float) $this->pack_size, 3, '.', ''), '0'), '.');

            return trim($size.' '.($this->packUnit?->code ?? ''));
        });
    }

    /**
     * Product name including its pack size, e.g. "Aashirvaad Atta 5 kg".
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->name.' '.($this->pack_label ?? '')));
    }

    protected function stockValue(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->current_stock * (float) $this->cost_price, 2));
    }

    /**
     * The selling price with VAT stripped out, whichever way it was entered.
     */
    protected function priceExcludingTax(): Attribute
    {
        return Attribute::get(function (): float {
            $price = (float) $this->selling_price;

            return $this->price_includes_tax
                ? round($price / (1 + (float) $this->tax_rate / 100), 2)
                : round($price, 2);
        });
    }

    /**
     * What the customer actually pays at the till.
     */
    protected function priceIncludingTax(): Attribute
    {
        return Attribute::get(function (): float {
            $price = (float) $this->selling_price;

            return $this->price_includes_tax
                ? round($price, 2)
                : round($price * (1 + (float) $this->tax_rate / 100), 2);
        });
    }

    protected function taxAmount(): Attribute
    {
        return Attribute::get(fn (): float => round($this->price_including_tax - $this->price_excluding_tax, 2));
    }

    /**
     * Margin is measured on the net selling price — VAT is not the store's money.
     */
    protected function marginPercent(): Attribute
    {
        return Attribute::get(function (): ?float {
            if ((float) $this->cost_price <= 0) {
                return null;
            }

            return round((($this->price_excluding_tax - (float) $this->cost_price) / (float) $this->cost_price) * 100, 2);
        });
    }

    public function isOutOfStock(): bool
    {
        return (float) $this->current_stock <= 0;
    }

    public function isLowOnStock(): bool
    {
        return ! $this->isOutOfStock()
            && (float) $this->reorder_level > 0
            && (float) $this->current_stock <= (float) $this->reorder_level;
    }

    public function stockStatus(): string
    {
        return match (true) {
            $this->isOutOfStock() => 'out_of_stock',
            $this->isLowOnStock() => 'low_stock',
            default => 'in_stock',
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->whereColumn('current_stock', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0)
            ->where('current_stock', '>', 0);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeOutOfStock(Builder $query): void
    {
        $query->where('current_stock', '<=', 0);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('short_name', 'like', "%{$term}%");
            });
        });
    }
}
