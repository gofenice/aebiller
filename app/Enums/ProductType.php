<?php

namespace App\Enums;

enum ProductType: string
{
    case Packaged = 'packaged';
    case Loose = 'loose';

    public function label(): string
    {
        return match ($this) {
            self::Packaged => 'Packet / Packaged',
            self::Loose => 'Loose / Weighed',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Packaged => 'Fixed pack sold per piece — biscuits, soap, 1 L milk, 5 kg rice bag.',
            self::Loose => 'Sold by weight or volume — vegetables, fruits, grains, spices.',
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
