<?php

namespace App\Enums;

enum CardStatus: string
{
    case Active = 'active';
    case Replaced = 'replaced';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Replaced => 'Replaced',
            self::Lost => 'Reported lost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Replaced => 'slate',
            self::Lost => 'red',
        };
    }
}
