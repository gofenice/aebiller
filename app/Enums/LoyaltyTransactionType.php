<?php

namespace App\Enums;

enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case WelcomeBonus = 'welcome_bonus';
    case BirthdayBonus = 'birthday_bonus';
    case Adjustment = 'adjustment';
    case EarnReversal = 'earn_reversal';
    case RedeemRefund = 'redeem_refund';
    case Expiry = 'expiry';

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'Points earned',
            self::Redeem => 'Points redeemed',
            self::WelcomeBonus => 'Welcome bonus',
            self::BirthdayBonus => 'Birthday bonus',
            self::Adjustment => 'Manual adjustment',
            self::EarnReversal => 'Earned points reversed',
            self::RedeemRefund => 'Redeemed points returned',
            self::Expiry => 'Points expired',
        };
    }

    /**
     * Badge colour on the points history.
     */
    public function color(): string
    {
        return match ($this) {
            self::Earn => 'green',
            self::Redeem => 'blue',
            self::WelcomeBonus, self::BirthdayBonus => 'violet',
            self::Adjustment => 'amber',
            self::EarnReversal => 'red',
            self::RedeemRefund, self::Expiry => 'slate',
        };
    }

    /**
     * The types that hand out new points, as opposed to moving existing ones.
     *
     * @return array<int, self>
     */
    public static function issuing(): array
    {
        return [self::Earn, self::WelcomeBonus, self::BirthdayBonus];
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
