<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case Damage = 'damage';
    case Expiry = 'expiry';
    case Wastage = 'wastage';
    case Theft = 'theft';
    case StockCount = 'stock_count';
    case StoreUse = 'store_use';
    case PurchaseReturn = 'purchase_return';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damage => 'Damaged goods',
            self::Expiry => 'Expired goods',
            self::Wastage => 'Wastage / spoilage',
            self::Theft => 'Theft / shrinkage',
            self::StockCount => 'Physical stock count',
            self::StoreUse => 'Store / staff use',
            self::PurchaseReturn => 'Return to supplier',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $reason): array => [$reason->value => $reason->label()])
            ->all();
    }
}
