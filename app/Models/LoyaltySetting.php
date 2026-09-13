<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The rules of the loyalty programme. There is only ever one row.
 */
#[Fillable([
    'is_enabled', 'program_name', 'points_per_currency', 'point_value', 'min_redeem_points',
    'max_redeem_percent', 'points_expiry_months', 'welcome_bonus', 'birthday_bonus',
    'tier_window_months', 'card_terms',
])]
class LoyaltySetting extends Model
{
    use BelongsToStore;

    /**
     * Mirrors the column defaults, so a row created on first use is complete
     * without being read back.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
        'program_name' => 'Fathima Rewards',
        'points_per_currency' => 1,
        'point_value' => 0.01,
        'min_redeem_points' => 100,
        'max_redeem_percent' => 50,
        'points_expiry_months' => 12,
        'welcome_bonus' => 50,
        'birthday_bonus' => 100,
        'tier_window_months' => 12,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'points_per_currency' => 'decimal:2',
            'point_value' => 'decimal:4',
            'min_redeem_points' => 'integer',
            'max_redeem_percent' => 'decimal:2',
            'points_expiry_months' => 'integer',
            'welcome_bonus' => 'integer',
            'birthday_bonus' => 'integer',
            'tier_window_months' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->oldest('id')->first() ?? static::create();
    }

    /**
     * What a number of points takes off a bill.
     */
    public function pointsWorth(int $points): float
    {
        return round($points * (float) $this->point_value, 2);
    }

    /**
     * When points credited at the given moment lapse, or null when points never expire.
     */
    public function expiryFrom(CarbonInterface $creditedAt): ?Carbon
    {
        if ($this->points_expiry_months <= 0) {
            return null;
        }

        return Carbon::parse($creditedAt)->addMonthsNoOverflow($this->points_expiry_months)->endOfDay();
    }

    /**
     * The rules in the shape the till's JavaScript needs.
     *
     * @return array{enabled: bool, points_per_currency: float, point_value: float, min_redeem_points: int, max_redeem_percent: float}
     */
    public function forTill(): array
    {
        return [
            'enabled' => $this->is_enabled,
            'points_per_currency' => (float) $this->points_per_currency,
            'point_value' => (float) $this->point_value,
            'min_redeem_points' => $this->min_redeem_points,
            'max_redeem_percent' => (float) $this->max_redeem_percent,
        ];
    }

    /**
     * The small print on the back of the card, falling back to a sensible default.
     */
    public function cardTerms(): string
    {
        if (filled($this->card_terms)) {
            return $this->card_terms;
        }

        $expiry = $this->points_expiry_months > 0
            ? "Points expire {$this->points_expiry_months} months after they are earned."
            : 'Points do not expire.';

        return 'Show this card or your registered mobile number at the till to earn points on every purchase. '
            .$expiry.' The card is not a payment card and remains the property of '.config('app.name').'.';
    }
}
