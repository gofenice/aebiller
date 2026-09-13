<?php

namespace App\Models;

use App\Enums\CardTheme;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\LoyaltyTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'min_spend', 'earn_multiplier', 'card_theme', 'perks', 'sort_order'])]
class LoyaltyTier extends Model
{
    /** @use HasFactory<LoyaltyTierFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_spend' => 'decimal:2',
            'earn_multiplier' => 'decimal:2',
            'card_theme' => CardTheme::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @param  Builder<LoyaltyTier>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('min_spend')->orderBy('sort_order');
    }

    /**
     * The tier every new member starts on.
     */
    public static function entry(): ?self
    {
        return static::query()->ordered()->first();
    }

    /**
     * The highest tier the given spend qualifies for.
     */
    public static function forSpend(float $spend): ?self
    {
        return static::query()
            ->where('min_spend', '<=', $spend)
            ->orderByDesc('min_spend')
            ->first() ?? static::entry();
    }

    /**
     * The next tier up and how much more spend it needs, for the member page.
     *
     * @return array{tier: LoyaltyTier, remaining: float}|null
     */
    public static function nextAfter(?self $tier, float $spend): ?array
    {
        $next = static::query()
            ->where('min_spend', '>', $tier !== null ? (float) $tier->min_spend : -1)
            ->ordered()
            ->first();

        if ($next === null) {
            return null;
        }

        return ['tier' => $next, 'remaining' => round(max((float) $next->min_spend - $spend, 0), 2)];
    }
}
