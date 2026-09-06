<?php

namespace App\Enums;

enum MovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case SalesReturn = 'sales_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case Sale = 'sale';
    case PurchaseReturn = 'purchase_return';
    case EntryReversal = 'entry_reversal';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening stock',
            self::Purchase => 'Purchase',
            self::SalesReturn => 'Sales return',
            self::AdjustmentIn => 'Adjustment (in)',
            self::AdjustmentOut => 'Adjustment (out)',
            self::Sale => 'Sale',
            self::PurchaseReturn => 'Purchase return',
            self::EntryReversal => 'Entry reversal',
        };
    }

    public function isInward(): bool
    {
        return in_array($this, [self::Opening, self::Purchase, self::SalesReturn, self::AdjustmentIn], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
