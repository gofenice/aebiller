<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Enums\StoreStatus;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One shop paying for the system. Its subdomain — fathima.pgbiller.com — is
 * what tells a request whose data to serve, and its own name, currency and
 * VAT number are what that store's screens and receipts show.
 */
#[Fillable([
    'name', 'slug', 'legal_name', 'status', 'suspension_reason', 'suspended_at',
    'owner_name', 'owner_email', 'owner_phone', 'currency_code', 'currency_symbol',
    'timezone', 'phone_country_code', 'vat_number', 'address', 'phone', 'email', 'expiry_alert_days',
    'tax_rates', 'trial_ends_on', 'notes',
    // What this store pays the platform.
    'plan_id', 'monthly_fee', 'billing_currency', 'billing_period', 'billing_day',
    'invoice_lead_days', 'grace_days', 'auto_suspend', 'billing_starts_on', 'next_invoice_on',
    'prorate_first_invoice', 'auto_charge_enabled', 'razorpay_subscription_id', 'razorpay_subscription_status',
])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'suspended_at' => 'datetime',
            'trial_ends_on' => 'date',
            'expiry_alert_days' => 'integer',
            'tax_rates' => 'array',
            'monthly_fee' => 'decimal:2',
            'billing_period' => BillingPeriod::class,
            'billing_day' => 'integer',
            'invoice_lead_days' => 'integer',
            'prorate_first_invoice' => 'boolean',
            'auto_charge_enabled' => 'boolean',
            'grace_days' => 'integer',
            'auto_suspend' => 'boolean',
            'billing_starts_on' => 'date',
            'next_invoice_on' => 'date',
        ];
    }

    /**
     * Stores are addressed by their subdomain rather than their id.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<StoreInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(StoreInvoice::class);
    }

    /**
     * @return HasMany<StorePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(StorePayment::class);
    }

    /**
     * What this store is billed each period: its own agreed fee if it has one,
     * otherwise the price of its plan.
     */
    public function periodFee(): float
    {
        if ($this->monthly_fee !== null) {
            return (float) $this->monthly_fee;
        }

        // The plan's price in the currency this store is billed in and for the
        // period it pays on — set by hand per currency, so it is a real price
        // rather than a conversion.
        return $this->plan !== null
            ? $this->plan->amountIn($this->billingCurrency(), $this->billingPeriod())['amount']
            : 0.0;
    }

    /**
     * Monthly, yearly or lifetime — the store's own setting, else its plan's.
     */
    public function billingPeriod(): BillingPeriod
    {
        return $this->billing_period ?? $this->plan?->billing_period ?? BillingPeriod::Monthly;
    }

    public function feeLabel(): string
    {
        return $this->billedCurrency().' '.number_format($this->periodFee(), 2).' '.$this->billingPeriod()->suffix();
    }

    public function invoiceLeadDays(): int
    {
        return (int) ($this->invoice_lead_days ?? 7);
    }

    /**
     * A card is on file with Razorpay and being charged each period.
     */
    public function autoChargeIsRunning(): bool
    {
        return $this->auto_charge_enabled
            && filled($this->razorpay_subscription_id)
            && in_array($this->razorpay_subscription_status, ['active', 'authenticated'], true);
    }

    /**
     * Waiting for the shopkeeper to authorise the card on Razorpay's page.
     */
    public function autoChargeIsPending(): bool
    {
        return filled($this->razorpay_subscription_id) && ! $this->autoChargeIsRunning()
            && ! in_array($this->razorpay_subscription_status, ['cancelled', 'completed', 'halted'], true);
    }

    /**
     * The unpaid invoice that closes the shop: past its due date by more than
     * the grace period. Only when the store is set to close automatically.
     */
    public function lockingInvoice(): ?StoreInvoice
    {
        if (! $this->auto_suspend) {
            return null;
        }

        return $this->invoices()
            ->unpaid()
            ->get()
            ->first(fn (StoreInvoice $invoice): bool => today()->greaterThan($invoice->suspendOn()));
    }

    /**
     * Suspended by hand, or locked because an invoice went unpaid.
     */
    public function isLocked(): bool
    {
        return $this->isSuspended() || $this->lockingInvoice() !== null;
    }

    /**
     * What the shop is asked to pay next, before it falls due.
     */
    public function dueSoonInvoice(): ?StoreInvoice
    {
        return $this->invoices()
            ->unpaid()
            ->whereDate('due_on', '>=', today())
            ->orderBy('due_on')
            ->first();
    }

    public function billingCurrency(): string
    {
        return $this->billing_currency ?: ($this->plan?->currency_code ?: $this->currency_code);
    }

    /**
     * A plan priced in the store's billing currency charges that price; one
     * that is not falls back to the plan's base currency, so the invoice and
     * the amount always agree.
     */
    public function billedCurrency(): string
    {
        if ($this->monthly_fee !== null || $this->plan === null) {
            return $this->billingCurrency();
        }

        return $this->plan->amountIn($this->billingCurrency(), $this->billingPeriod())['currency'];
    }

    /**
     * Still inside the free trial, and has never paid anything.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_on !== null
            && $this->trial_ends_on->endOfDay()->isFuture()
            && ! $this->invoices()->where('status', InvoiceStatus::Paid)->exists();
    }

    public function trialDaysLeft(): int
    {
        return $this->trial_ends_on === null ? 0 : max(0, (int) ceil(today()->diffInDays($this->trial_ends_on, false)));
    }

    public function isBillable(): bool
    {
        return $this->periodFee() > 0 && $this->next_invoice_on !== null;
    }

    /**
     * fathima.pgbiller.com
     */
    public function host(): string
    {
        return $this->slug.'.'.config('tenancy.central_domain');
    }

    public function url(): string
    {
        return (str_starts_with((string) config('app.url'), 'https') ? 'https://' : 'http://').$this->host();
    }

    public function isActive(): bool
    {
        return $this->status === StoreStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === StoreStatus::Suspended;
    }

    /**
     * The store's own details, in the config keys the screens already read —
     * so a receipt, a shelf label and a loyalty card all carry this store's
     * name, currency and VAT number without any screen knowing about stores.
     *
     * @return array<string, mixed>
     */
    public function configOverrides(): array
    {
        $defaults = config('tenancy.defaults');

        return [
            'app.name' => $this->name,
            'app.timezone' => $this->timezone ?: config('app.timezone'),
            'inventory.currency_symbol' => $this->currency_symbol ?: $defaults['currency_symbol'],
            'inventory.currency_code' => $this->currency_code ?: $defaults['currency_code'],
            'inventory.store_vat_number' => $this->vat_number,
            'inventory.store_address' => $this->address,
            'inventory.store_phone' => $this->phone,
            'inventory.expiry_alert_days' => $this->expiry_alert_days ?: $defaults['expiry_alert_days'],
            'inventory.tax_rates' => $this->tax_rates ?: $defaults['tax_rates'],
        ];
    }

    public function applyToConfig(): void
    {
        config($this->configOverrides());

        if (filled($this->timezone)) {
            date_default_timezone_set($this->timezone);
        }
    }

    /**
     * @param  Builder<Store>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', StoreStatus::Active);
    }
}
