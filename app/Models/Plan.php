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
 * A tier a shop subscribes to. Platform-level: never scoped to a store.
 *
 * Priced per month in each currency; the yearly price is those twelve months
 * less the plan's own discount, so there is one figure to keep up to date
 * rather than two that can drift apart.
 */
#[Fillable([
    'name', 'slug', 'monthly_price', 'yearly_discount_percent', 'billing_period',
    'razorpay_plan_id', 'razorpay_yearly_plan_id', 'currency_code', 'description',
    'is_active', 'is_public', 'is_free', 'sort_order',
    'max_products', 'max_monthly_bills', 'max_users', 'max_customers',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * What a plan may cap, and what each cap is called on screen. A column left
     * null means that thing is unlimited on this plan.
     *
     * @var array<string, string>
     */
    public const LIMITS = [
        'max_products' => 'Products',
        'max_monthly_bills' => 'Bills per month',
        'max_users' => 'Staff accounts',
        'max_customers' => 'Loyalty members',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_discount_percent' => 'decimal:2',
            'billing_period' => BillingPeriod::class,
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_free' => 'boolean',
            'sort_order' => 'integer',
            'max_products' => 'integer',
            'max_monthly_bills' => 'integer',
            'max_users' => 'integer',
            'max_customers' => 'integer',
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
     * Monthly prices set by hand for other currencies. No exchange rates: a
     * price only changes when someone changes it.
     *
     * @return HasMany<PlanPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * What this plan costs for one period in one currency, falling back to the
     * base price when that currency has not been priced.
     *
     * @return array{amount: float, currency: string, is_base: bool}
     */
    public function amountIn(?string $currency = null, ?BillingPeriod $period = null): array
    {
        $monthly = $this->monthlyAmountIn($currency ?: config('tenancy.base_currency'));
        $period ??= $this->billing_period;

        if ($period === BillingPeriod::Yearly && $this->offersYearly()) {
            return [...$monthly, 'amount' => $this->yearlyFrom($monthly['amount'])];
        }

        return $monthly;
    }

    /**
     * The monthly price everything else is worked out from.
     *
     * @return array{amount: float, currency: string, is_base: bool}
     */
    protected function monthlyAmountIn(string $currency): array
    {
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
     * Twelve months, less the discount that makes paying yearly worth it.
     */
    public function yearlyFrom(float $monthly): float
    {
        return round($monthly * 12 * (1 - $this->yearlyDiscount() / 100), 2);
    }

    public function yearlyDiscount(): float
    {
        return max(0.0, min(100.0, (float) $this->yearly_discount_percent));
    }

    /**
     * A free or one-off plan has no yearly alternative to offer.
     */
    public function offersYearly(): bool
    {
        return ! $this->is_free && $this->billing_period === BillingPeriod::Monthly;
    }

    /**
     * What a year on this plan saves against paying by the month.
     */
    public function yearlySavingIn(?string $currency = null): float
    {
        $monthly = $this->monthlyAmountIn($currency ?: config('tenancy.base_currency'));

        return round($monthly['amount'] * 12 - $this->yearlyFrom($monthly['amount']), 2);
    }

    /**
     * "$39 / month", in whichever currency and period is being shown.
     */
    public function priceLabelIn(?string $currency = null, ?BillingPeriod $period = null): string
    {
        $price = $this->amountIn($currency, $period);
        $period ??= $this->billing_period;

        return DisplayCurrency::format($price['amount'], $price['currency']).' '.$period->suffix();
    }

    /**
     * "SAR 199.00 / month", or "SAR 4,999.00 once" for a lifetime plan.
     */
    public function priceLabel(): string
    {
        return $this->currency_code.' '.number_format((float) $this->monthly_price, 2).' '.$this->billing_period->suffix();
    }

    /**
     * How many of something this plan allows, or null for no limit at all.
     */
    public function limitFor(string $key): ?int
    {
        $limit = $this->getAttribute($key);

        return $limit === null ? null : (int) $limit;
    }

    /**
     * Whether one more may be added, given how many there are now.
     */
    public function allows(string $key, int $current): bool
    {
        $limit = $this->limitFor($key);

        return $limit === null || $current < $limit;
    }

    public function limitLabel(string $key): string
    {
        $limit = $this->limitFor($key);

        return $limit === null ? 'Unlimited' : number_format($limit);
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Plans a shop may choose for itself. The free lifetime plan is given out
     * by hand from the platform, never picked off the pricing table.
     *
     * @param  Builder<Plan>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('monthly_price');
    }
}
