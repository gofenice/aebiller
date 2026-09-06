<?php

namespace App\Enums;

enum StorageType: string
{
    case Ambient = 'ambient';
    case Chilled = 'chilled';
    case Frozen = 'frozen';

    public function label(): string
    {
        return match ($this) {
            self::Ambient => 'Ambient / Dry',
            self::Chilled => 'Chilled (2-8°C)',
            self::Frozen => 'Frozen (-18°C)',
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
