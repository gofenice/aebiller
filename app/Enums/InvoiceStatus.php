<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Issued = 'issued';
    case PartlyPaid = 'partly_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::PartlyPaid => 'Part paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Void => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Issued => 'blue',
            self::PartlyPaid => 'amber',
            self::Paid => 'green',
            self::Overdue => 'red',
            self::Void => 'slate',
        };
    }

    /**
     * Invoices still owed.
     *
     * @return array<int, self>
     */
    public static function unpaid(): array
    {
        return [self::Issued, self::PartlyPaid, self::Overdue];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
