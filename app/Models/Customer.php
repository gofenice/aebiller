<?php

namespace App\Models;

use App\Enums\CardStatus;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A loyalty member. The points figures on this row are kept in step by
 * LoyaltyService, which is the only thing that should change them.
 */
#[Fillable([
    'uuid', 'name', 'phone', 'email', 'birth_date', 'city', 'address',
    'marketing_opt_in', 'is_active', 'notes', 'loyalty_tier_id', 'enrolled_by',
    'opening_due', 'opening_due_outstanding', 'opening_due_on', 'opening_due_note',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToStore, HasFactory, HasUuids;

    /**
     * Only the public uuid is generated; the numeric id stays the primary key.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'marketing_opt_in' => 'boolean',
            'is_active' => 'boolean',
            'points_balance' => 'integer',
            'visits' => 'integer',
            'lifetime_spend' => 'decimal:2',
            'last_visit_at' => 'datetime',
            'opening_due' => 'decimal:2',
            'opening_due_outstanding' => 'decimal:2',
            'opening_due_on' => 'date',
        ];
    }

    /**
     * Everything this member owes: what was carried over from the shop's old
     * book, plus every credit bill still unpaid.
     */
    public function totalDue(): float
    {
        return round((float) $this->opening_due_outstanding + (float) $this->billsDue(), 2);
    }

    /**
     * What is still owed on this member's credit bills.
     */
    public function billsDue(): float
    {
        return (float) $this->sales()->outstanding()->sum('amount_outstanding');
    }

    /**
     * Members who owe the shop money, carried over or on a bill.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeOwing(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('opening_due_outstanding', '>', 0)
            ->orWhereHas('sales', fn (Builder $sales) => $sales->outstanding()));
    }

    /**
     * Phones are stored as bare digits so "055 123 4567", "+966 55 123 4567"
     * and "0551234567" are all the same member.
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : static::normalizePhone($value),
        );
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '966') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '0'.$digits;
        }

        return $digits;
    }

    /**
     * 0551234567 → 055 123 4567.
     */
    public function formattedPhone(): string
    {
        if (strlen($this->phone) === 10) {
            return substr($this->phone, 0, 3).' '.substr($this->phone, 3, 3).' '.substr($this->phone, 6);
        }

        return $this->phone;
    }

    public function firstName(): string
    {
        return str($this->name)->before(' ')->toString();
    }

    /**
     * @return BelongsTo<LoyaltyTier, $this>
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'loyalty_tier_id');
    }

    /**
     * @return HasMany<LoyaltyCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class)->latest('id');
    }

    /**
     * The one card that scans at the till; LoyaltyService retires the old
     * card whenever it issues a new one.
     *
     * @return HasOne<LoyaltyCard, $this>
     */
    public function activeCard(): HasOne
    {
        return $this->hasOne(LoyaltyCard::class)->where('status', CardStatus::Active);
    }

    /**
     * @return HasMany<LoyaltyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Money taken against this member's credit — a bill of theirs, or the
     * balance carried over from before the shop billed here.
     *
     * @return HasMany<CreditPayment, $this>
     */
    public function creditPayments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enroller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /**
     * What the current balance would take off a bill.
     */
    public function pointsValue(?LoyaltySetting $settings = null): float
    {
        return ($settings ?? LoyaltySetting::current())->pointsWorth(max($this->points_balance, 0));
    }

    /**
     * The address in the QR code on the back of the card: the member's own
     * balance page, no sign-in needed.
     */
    public function publicUrl(): string
    {
        return route('member.show', $this->uuid);
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Name, phone or card number.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $query->where(function (Builder $query) use ($term, $digits): void {
            $query->where('name', 'like', "%{$term}%");

            if (strlen($digits) >= 3) {
                $query->orWhere('phone', 'like', '%'.ltrim(static::normalizePhone($digits), '0').'%')
                    ->orWhereHas('cards', fn (Builder $cards) => $cards->where('number', 'like', "%{$digits}%"));
            }
        });
    }
}
