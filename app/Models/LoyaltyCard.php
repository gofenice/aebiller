<?php

namespace App\Models;

use App\Enums\CardStatus;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\LoyaltyCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'number', 'status', 'issued_at', 'retired_at', 'retired_reason', 'issued_by'])]
class LoyaltyCard extends Model
{
    /** @use HasFactory<LoyaltyCardFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CardStatus::class,
            'issued_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isActive(): bool
    {
        return $this->status === CardStatus::Active;
    }

    /**
     * 2900000000017 → 2900 0000 0001 7, the way it is embossed on the card.
     */
    public function formattedNumber(): string
    {
        return implode(' ', str_split($this->number, 4));
    }

    /**
     * Enough to recognise the card on a receipt without printing all of it.
     */
    public function maskedNumber(): string
    {
        return '•••• '.substr($this->number, -4);
    }
}
