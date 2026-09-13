<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Support\DisplayCurrency;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a store pays per month. Platform-level: never scoped to a store.
 */
#[Fillable([
    'name', 'slug', 'monthly_price', 'billing_period', 'razorpay_plan_id',
    'currency_code', 'description', 'is_active', 'sort_order',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'billing_period' => BillingPeriod::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Store, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Prices set by hand for other currencies. No exchange rates: a price only
     * changes when someone changes it.
     *
     * @return HasMany<PlanPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * What this plan costs in one currency, falling back to the base price
     * when that currency has not been priced.
     *
     * @return array{amount: float, currency: string, is_base: bool}
     */
    public function amountIn(?string $currency = null): array
    {
        $currency = $currency ?: config('tenancy.base_currency');

        if ($currency === $this->currency_code) {
            return ['amount' => (float) $this->monthly_price, 'currency' => $this->currency_code, 'is_base' => true];
        }

        $price = $this->relationLoaded('prices')
            ? $this->prices->firstWhere('currency_code', $currency)
            : $this->prices()->where('currency_code', $currency)->first();

        return $price !== null
            ? ['amount' => (float) $price->amount, 'currency' => $currency, 'is_base' => false]
            : ['amount' => (float) $this->monthly_price, 'currency' => $this->currency_code, 'is_base' => true];
    }

    /**
     * "$39 / month", in whichever currency is being shown.
     */
    public function priceLabelIn(?string $currency = null): string
    {
        $price = $this->amountIn($currency);

        return DisplayCurrency::format($price['amount'], $price['currency']).' '.$this->billing_period->suffix();
    }

    /**
     * "SAR 199.00 / month", or "SAR 4,999.00 once" for a lifetime plan.
     */
    public function priceLabel(): string
    {
        return $this->currency_code.' '.number_format((float) $this->monthly_price, 2).' '.$this->billing_period->suffix();
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('monthly_price');
    }
}
