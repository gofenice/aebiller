<?php

namespace App\Enums;

/**
 * How a store paid its subscription — distinct from PaymentMethod, which is
 * how a shopper paid at a till.
 */
enum PaymentMethodType: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Card = 'card';
    case Cheque = 'cheque';
    case Razorpay = 'razorpay';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Cheque => 'Cheque',
            self::Razorpay => 'Razorpay (online)',
            self::Other => 'Other',
        };
    }

    /**
     * Paid by the store itself through the gateway, rather than entered by hand.
     */
    public function isOnline(): bool
    {
        return $this === self::Razorpay;
    }

    /**
     * The methods offered when recording a payment by hand.
     *
     * @return array<int, self>
     */
    public static function manual(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method): bool => ! $method->isOnline()));
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
