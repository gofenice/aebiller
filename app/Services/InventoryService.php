<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\StockEntryType;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockEntry;
use App\Models\StockEntryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Central place where every stock quantity change happens.
 *
 * Nothing else in the application should write to `products.current_stock`
 * or `stock_batches.quantity` directly — going through this service keeps the
 * stock ledger (`stock_movements`) complete and auditable.
 */
class InventoryService
{
    public function __construct(protected ReferenceNumbers $references) {}

    /**
     * Post a goods-received / opening-stock / sales-return document into stock.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createStockEntry(array $attributes, array $lines, User $user): StockEntry
    {
        if ($lines === []) {
            throw new RuntimeException('A stock entry needs at least one product line.');
        }

        return DB::transaction(function () use ($attributes, $lines, $user): StockEntry {
            $type = $attributes['type'] instanceof StockEntryType
                ? $attributes['type']
                : StockEntryType::from($attributes['type']);

            $entry = StockEntry::create([
                'reference_no' => $this->nextReference(StockEntry::class, $this->entryPrefix($type)),
                'type' => $type,
                'entry_date' => $attributes['entry_date'],
                'supplier_id' => $attributes['supplier_id'] ?? null,
                'invoice_number' => $attributes['invoice_number'] ?? null,
                'invoice_date' => $attributes['invoice_date'] ?? null,
                'other_charges' => $attributes['other_charges'] ?? 0,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $subtotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;

            foreach ($lines as $line) {
                $product = Product::lockForUpdate()->findOrFail($line['product_id']);

                $quantity = (float) $line['quantity'];
                $freeQuantity = (float) ($line['free_quantity'] ?? 0);
                $unitCost = (float) ($line['unit_cost'] ?? 0);
                $discountPercent = (float) ($line['discount_percent'] ?? 0);
                $taxPercent = (float) ($line['tax_percent'] ?? $product->tax_rate);

                $gross = $quantity * $unitCost;
                $discount = round($gross * $discountPercent / 100, 2);
                $net = round($gross - $discount, 2);
                $tax = round($net * $taxPercent / 100, 2);

                $subtotal += $net;
                $discountTotal += $discount;
                $taxTotal += $tax;

                $batch = $this->resolveBatchForEntry($product, $line, $entry, $unitCost);

                $item = $entry->items()->create([
                    'product_id' => $product->id,
                    'stock_batch_id' => $batch?->id,
                    'batch_number' => $line['batch_number'] ?? null,
                    'manufactured_on' => $line['manufactured_on'] ?? null,
                    'expires_on' => $line['expires_on'] ?? null,
                    'quantity' => $quantity,
                    'free_quantity' => $freeQuantity,
                    'unit_cost' => $unitCost,
                    'discount_percent' => $discountPercent,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $tax,
                    'line_total' => round($net + $tax, 2),
                    'selling_price' => $line['selling_price'] ?? null,
                ]);

                $received = $quantity + $freeQuantity;

                if ($batch !== null) {
                    $batch->increment('quantity', $received);
                    $batch->increment('received_quantity', $received);
                }

                $this->applyMovement(
                    product: $product,
                    type: $type->movementType(),
                    direction: 'in',
                    quantity: $received,
                    unitCost: $unitCost,
                    user: $user,
                    source: $entry,
                    batch: $batch,
                    reference: $entry->reference_no,
                    movedAt: Carbon::parse($entry->entry_date),
                );

                $this->syncProductPricing($product, $item);
            }

            $entry->update([
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'tax_total' => round($taxTotal, 2),
                'grand_total' => round($subtotal + $taxTotal + (float) $entry->other_charges, 2),
            ]);

            return $entry->fresh(['items.product', 'supplier']);
        });
    }

    /**
     * Post a stock adjustment (damage, expiry, wastage, physical count, ...).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createAdjustment(array $attributes, array $lines, User $user): StockAdjustment
    {
        if ($lines === []) {
            throw new RuntimeException('A stock adjustment needs at least one product line.');
        }

        return DB::transaction(function () use ($attributes, $lines, $user): StockAdjustment {
            $reason = $attributes['reason'] instanceof AdjustmentReason
                ? $attributes['reason']
                : AdjustmentReason::from($attributes['reason']);

            $adjustment = StockAdjustment::create([
                'reference_no' => $this->nextReference(StockAdjustment::class, 'ADJ'),
                'adjustment_date' => $attributes['adjustment_date'],
                'reason' => $reason,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $totalValue = 0.0;

            foreach ($lines as $line) {
                $product = Product::lockForUpdate()->findOrFail($line['product_id']);
                $direction = $line['direction'] === 'in' ? 'in' : 'out';
                $stockBefore = (float) $product->current_stock;

                $quantity = $reason === AdjustmentReason::StockCount
                    ? abs((float) $line['quantity'] - $stockBefore)
                    : abs((float) $line['quantity']);

                if ($reason === AdjustmentReason::StockCount) {
                    $direction = (float) $line['quantity'] >= $stockBefore ? 'in' : 'out';
                }

                if ($quantity <= 0) {
                    continue;
                }

                if ($direction === 'out' && $quantity > $stockBefore) {
                    throw new RuntimeException(
                        "Cannot remove {$quantity} from {$product->name}: only {$stockBefore} in stock."
                    );
                }

                $batch = isset($line['stock_batch_id'])
                    ? StockBatch::where('product_id', $product->id)->find($line['stock_batch_id'])
                    : null;

                $unitCost = (float) ($line['unit_cost'] ?? $batch?->cost_price ?? $product->cost_price);
                $value = round($quantity * $unitCost, 2);
                $totalValue += $value;

                if ($batch !== null) {
                    $direction === 'in'
                        ? $batch->increment('quantity', $quantity)
                        : $batch->decrement('quantity', min($quantity, (float) $batch->quantity));
                } elseif ($direction === 'out') {
                    $this->consumeStockFefo($product, $quantity);
                }

                $movement = $this->applyMovement(
                    product: $product,
                    type: $direction === 'in' ? MovementType::AdjustmentIn : MovementType::AdjustmentOut,
                    direction: $direction,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    user: $user,
                    source: $adjustment,
                    batch: $batch,
                    reference: $adjustment->reference_no,
                    notes: $line['notes'] ?? $reason->label(),
                    movedAt: Carbon::parse($adjustment->adjustment_date),
                );

                $adjustment->items()->create([
                    'product_id' => $product->id,
                    'stock_batch_id' => $batch?->id,
                    'direction' => $direction,
                    'quantity' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $movement->balance_after,
                    'unit_cost' => $unitCost,
                    'value' => $value,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            if ($adjustment->items()->count() === 0) {
                throw new RuntimeException('No stock changes were produced by this adjustment.');
            }

            $adjustment->update(['total_value' => round($totalValue, 2)]);

            return $adjustment->fresh(['items.product']);
        });
    }

    /**
     * Undo a posted entry: take the received quantity back out of stock and
     * delete the document. Refused when the goods have already been sold on.
     */
    public function reverseStockEntry(StockEntry $entry, User $user): void
    {
        DB::transaction(function () use ($entry, $user): void {
            $entry->loadMissing('items');

            foreach ($entry->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                $quantity = $item->totalQuantity();

                if ((float) $product->current_stock < $quantity) {
                    throw new RuntimeException(
                        "{$product->name} has only {$product->current_stock} left of the {$quantity} received — this entry can no longer be reversed."
                    );
                }

                $batch = $item->stock_batch_id !== null ? StockBatch::find($item->stock_batch_id) : null;

                if ($batch !== null) {
                    if ((float) $batch->quantity < $quantity) {
                        throw new RuntimeException(
                            "Batch {$batch->batch_number} of {$product->name} no longer holds the received quantity."
                        );
                    }

                    $batch->decrement('quantity', $quantity);
                    $batch->decrement('received_quantity', $quantity);
                }

                $this->applyMovement(
                    product: $product,
                    type: MovementType::EntryReversal,
                    direction: 'out',
                    quantity: $quantity,
                    unitCost: (float) $item->unit_cost,
                    user: $user,
                    batch: $batch,
                    reference: $entry->reference_no,
                    notes: "Reversal of {$entry->reference_no}",
                );
            }

            $entry->movements()->update(['source_type' => null, 'source_id' => null]);
            $entry->delete();
        });
    }

    /**
     * Record the opening stock captured on the product form.
     */
    public function recordOpeningStock(Product $product, float $quantity, User $user, ?string $expiresOn = null): ?StockMovement
    {
        if ($quantity <= 0) {
            return null;
        }

        return DB::transaction(function () use ($product, $quantity, $user, $expiresOn): StockMovement {
            $batch = null;

            if ($product->track_batches || $product->track_expiry) {
                $batch = $product->batches()->create([
                    'supplier_id' => $product->supplier_id,
                    'batch_number' => 'OPENING',
                    'expires_on' => $expiresOn,
                    'received_quantity' => $quantity,
                    'quantity' => $quantity,
                    'cost_price' => $product->cost_price,
                    'received_on' => now()->toDateString(),
                ]);
            }

            return $this->applyMovement(
                product: $product,
                type: MovementType::Opening,
                direction: 'in',
                quantity: $quantity,
                unitCost: (float) $product->cost_price,
                user: $user,
                batch: $batch,
                notes: 'Opening stock recorded on product creation',
            );
        });
    }

    /**
     * Take stock out for a sale or similar outward document, consuming the
     * oldest-expiring batches first.
     */
    public function issueStock(
        Product $product,
        float $quantity,
        MovementType $type,
        User $user,
        ?Model $source = null,
        ?string $reference = null,
        ?string $notes = null,
        ?Carbon $movedAt = null,
    ): StockMovement {
        $this->consumeStockFefo($product, $quantity);

        return $this->applyMovement(
            product: $product,
            type: $type,
            direction: 'out',
            quantity: $quantity,
            unitCost: (float) $product->cost_price,
            user: $user,
            source: $source,
            reference: $reference,
            notes: $notes,
            movedAt: $movedAt,
        );
    }

    /**
     * Put stock back after a void or a customer return. Batch-tracked items go
     * back into their nearest-expiry open batch.
     */
    public function returnStock(
        Product $product,
        float $quantity,
        MovementType $type,
        User $user,
        ?Model $source = null,
        ?string $reference = null,
        ?string $notes = null,
    ): StockMovement {
        $batch = null;

        if ($product->track_batches || $product->track_expiry) {
            $batch = $product->batches()
                ->orderByRaw('quantity <= 0')
                ->orderByRaw('expires_on is null, expires_on asc')
                ->first();

            $batch?->increment('quantity', $quantity);
        }

        return $this->applyMovement(
            product: $product,
            type: $type,
            direction: 'in',
            quantity: $quantity,
            unitCost: (float) $product->cost_price,
            user: $user,
            source: $source,
            batch: $batch,
            reference: $reference,
            notes: $notes,
        );
    }

    /**
     * Write one line to the stock ledger and move the product balance with it.
     */
    public function applyMovement(
        Product $product,
        MovementType $type,
        string $direction,
        float $quantity,
        float $unitCost,
        User $user,
        ?Model $source = null,
        ?StockBatch $batch = null,
        ?string $reference = null,
        ?string $notes = null,
        ?Carbon $movedAt = null,
    ): StockMovement {
        $signed = $direction === 'in' ? $quantity : -$quantity;
        $balanceAfter = round((float) $product->current_stock + $signed, 3);

        $product->forceFill(['current_stock' => $balanceAfter])->save();

        return StockMovement::create([
            'product_id' => $product->id,
            'stock_batch_id' => $batch?->id,
            'type' => $type,
            'direction' => $direction,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'unit_cost' => $unitCost,
            'source_type' => $source ? $source->getMorphClass() : null,
            'source_id' => $source?->getKey(),
            'reference' => $reference,
            'notes' => $notes,
            'user_id' => $user->id,
            'moved_at' => $movedAt ?? now(),
        ]);
    }

    /**
     * Next document number, e.g. GRN-2609-0007.
     *
     * @param  class-string<Model>  $model
     */
    public function nextReference(string $model, string $prefix, string $column = 'reference_no'): string
    {
        return $this->references->next($model, $prefix, $column);
    }

    /**
     * Remove quantity from the oldest-expiring batches first (FEFO).
     */
    protected function consumeStockFefo(Product $product, float $quantity): void
    {
        $remaining = $quantity;

        $batches = $product->batches()
            ->where('quantity', '>', 0)
            ->orderByRaw('expires_on is null, expires_on asc')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (float) $batch->quantity);
            $batch->decrement('quantity', $take);
            $remaining -= $take;
        }
    }

    /**
     * Find or create the batch a received line should land in.
     *
     * @param  array<string, mixed>  $line
     */
    protected function resolveBatchForEntry(Product $product, array $line, StockEntry $entry, float $unitCost): ?StockBatch
    {
        if (! $product->track_batches && ! $product->track_expiry) {
            return null;
        }

        $batchNumber = $line['batch_number'] ?? null;
        $expiresOn = $line['expires_on'] ?? null;

        if ($batchNumber === null && $expiresOn === null && $product->shelf_life_days !== null) {
            $expiresOn = Carbon::parse($entry->entry_date)->addDays($product->shelf_life_days)->toDateString();
        }

        $existing = $product->batches()
            ->where('batch_number', $batchNumber)
            ->whereDate('expires_on', $expiresOn ?? '1970-01-01')
            ->when($expiresOn === null, fn ($query) => $query->whereNull('expires_on'))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $product->batches()->create([
            'supplier_id' => $entry->supplier_id,
            'batch_number' => $batchNumber,
            'manufactured_on' => $line['manufactured_on'] ?? null,
            'expires_on' => $expiresOn,
            'received_quantity' => 0,
            'quantity' => 0,
            'cost_price' => $unitCost,
            'received_on' => $entry->entry_date,
        ]);
    }

    /**
     * Keep the product master in step with the latest purchase price.
     */
    protected function syncProductPricing(Product $product, StockEntryItem $item): void
    {
        $changes = [];

        if ((float) $item->unit_cost > 0 && (float) $item->unit_cost !== (float) $product->cost_price) {
            $changes['cost_price'] = $item->unit_cost;
        }

        if ($item->selling_price !== null && (float) $item->selling_price > 0) {
            $changes['selling_price'] = $item->selling_price;
        }

        if ($changes !== []) {
            $product->forceFill($changes)->save();
        }
    }

    protected function entryPrefix(StockEntryType $type): string
    {
        return match ($type) {
            StockEntryType::Purchase => 'GRN',
            StockEntryType::Opening => 'OPN',
            StockEntryType::SalesReturn => 'SRN',
        };
    }
}
