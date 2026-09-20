<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card (mada / Visa)',
            self::Transfer => 'Bank transfer',
            self::Credit => 'Credit (pay later)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Transfer => 'Transfer',
            self::Credit => 'Credit',
        };
    }

    /**
     * Only cash needs a tendered amount and change calculation.
     */
    public function needsTendering(): bool
    {
        return $this === self::Cash;
    }

    /**
     * The bill closes with money still owed on it.
     */
    public function isCredit(): bool
    {
        return $this === self::Credit;
    }

    /**
     * The ways money actually arrives, so credit cannot settle credit.
     *
     * @return array<int, self>
     */
    public static function settlementMethods(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method): bool => ! $method->isCredit()));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method): array => [$method->value => $method->label()])
            ->all();
    }
}
