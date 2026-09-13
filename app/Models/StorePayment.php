<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use Database\Factories\StorePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received from a store for its subscription.
 */
#[Fillable([
    'store_id', 'store_invoice_id', 'amount', 'currency_code', 'method',
    'reference', 'received_on', 'notes', 'recorded_by',
])]
class StorePayment extends Model
{
    /** @use HasFactory<StorePaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethodType::class,
            'amount' => 'decimal:2',
            'received_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<StoreInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StoreInvoice::class, 'store_invoice_id');
    }

    /**
     * @return BelongsTo<PlatformUser, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'recorded_by');
    }
}
