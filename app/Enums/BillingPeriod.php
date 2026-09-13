<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * How often a store pays. Lifetime is charged once and never invoiced again.
 */
enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
            self::Lifetime => 'Lifetime',
        };
    }

    /**
     * Written after a price: "SAR 199 / month".
     */
    public function suffix(): string
    {
        return match ($this) {
            self::Monthly => '/ month',
            self::Yearly => '/ year',
            self::Lifetime => 'once',
        };
    }

    public function isRecurring(): bool
    {
        return $this !== self::Lifetime;
    }

    /**
     * When the next payment falls due, or null when there will never be one.
     * The day is clamped so a store billed on the 31st is not skipped in
     * February.
     */
    public function nextRenewal(CarbonInterface $from, int $billingDay = 1): ?Carbon
    {
        if (! $this->isRecurring()) {
            return null;
        }

        $next = match ($this) {
            self::Monthly => Carbon::parse($from)->addMonthNoOverflow(),
            self::Yearly => Carbon::parse($from)->addYearNoOverflow(),
            default => null,
        };

        $day = min(max($billingDay ?: 1, 1), $next->daysInMonth);

        return $next->setDay($day)->startOfDay();
    }

    /**
     * The last day the period covers; a lifetime invoice covers no end date.
     */
    public function periodEnd(CarbonInterface $start): ?Carbon
    {
        return match ($this) {
            self::Monthly => Carbon::parse($start)->addMonthNoOverflow()->subDay(),
            self::Yearly => Carbon::parse($start)->addYearNoOverflow()->subDay(),
            self::Lifetime => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $period): array => [$period->value => $period->label()])
            ->all();
    }
}
