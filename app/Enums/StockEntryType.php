<?php

namespace App\Enums;

enum StockEntryType: string
{
    case Purchase = 'purchase';
    case Opening = 'opening';
    case SalesReturn = 'sales_return';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase / GRN',
            self::Opening => 'Opening Stock',
            self::SalesReturn => 'Sales Return',
        };
    }

    public function movementType(): MovementType
    {
        return match ($this) {
            self::Purchase => MovementType::Purchase,
            self::Opening => MovementType::Opening,
            self::SalesReturn => MovementType::SalesReturn,
        };
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
