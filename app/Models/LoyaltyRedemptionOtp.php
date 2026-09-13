<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Database\Factories\LoyaltyRedemptionOtpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The one-time code that authorises a member's points being spent.
 *
 * The code itself is never stored — only its hash — so a copy of the table is
 * not a set of working codes. A row is tied to the member and to the exact
 * number of points it was issued for: a code approved for 100 points cannot be
 * turned into a 5,000 point redemption on the way to the till.
 */
#[Fillable([
    'customer_id', 'points', 'code_hash', 'sent_to', 'expires_at', 'verified_at',
    'consumed_at', 'sale_id', 'attempts', 'issued_by', 'overridden_by', 'override_reason',
])]
class LoyaltyRedemptionOtp extends Model
{
    use BelongsToStore;

    /** @use HasFactory<LoyaltyRedemptionOtpFactory> */
    use HasFactory;

    /**
     * Six digits: long enough not to be guessed inside the attempt limit,
     * short enough to read aloud across a counter.
     */
    public const CODE_LENGTH = 6;

    /**
     * Wrong guesses allowed before the code is dead and a new one is needed.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attempts' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /**
     * Issue a code for this member and this many points.
     *
     * @return array{otp: self, code: string}
     */
    public static function issueFor(Customer $customer, int $points, User $cashier, ?string $sentTo = null): array
    {
        // Any code still outstanding for this member is spent: one live code
        // at a time, so an older message cannot be used later.
        static::query()
            ->where('customer_id', $customer->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        $otp = static::create([
            'customer_id' => $customer->id,
            'points' => $points,
            'code_hash' => Hash::make($code),
            'sent_to' => $sentTo,
            'expires_at' => now()->addMinutes((int) config('services.whatsapp.otp_minutes', 5)),
            'issued_by' => $cashier->id,
        ]);

        return ['otp' => $otp, 'code' => $code];
    }

    /**
     * Approved by the shop owner instead of the member, because the code could
     * not be delivered. Recorded with who did it and why.
     */
    public static function overrideFor(Customer $customer, int $points, User $owner, string $reason): self
    {
        return static::create([
            'customer_id' => $customer->id,
            'points' => $points,
            // No code was ever issued, so nothing can match.
            'code_hash' => Hash::make(Str::random(40)),
            'expires_at' => now()->addMinutes((int) config('services.whatsapp.otp_minutes', 5)),
            'verified_at' => now(),
            'issued_by' => $owner->id,
            'overridden_by' => $owner->id,
            'override_reason' => $reason,
        ]);
    }

    /**
     * Still worth trying: not used, not expired, not guessed at too often.
     */
    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->attempts < self::MAX_ATTEMPTS
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Check a code the cashier typed. Every attempt is counted, right or
     * wrong, so guessing runs out.
     */
    public function verify(string $code): bool
    {
        if (! $this->isUsable()) {
            return false;
        }

        $this->increment('attempts');

        if (! Hash::check($code, $this->code_hash)) {
            return false;
        }

        $this->forceFill(['verified_at' => now()])->save();

        return true;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null && $this->consumed_at === null;
    }

    /**
     * Whether this approval actually covers the redemption being attempted.
     *
     * The points matter as much as the code: without this check a code the
     * member approved for 100 points would settle a 5,000 point redemption
     * submitted straight afterwards.
     */
    public function isApprovalFor(Customer $customer, int $points): bool
    {
        return $this->customer_id === $customer->id
            && $this->isVerified()
            && $points <= $this->points
            // A basket takes a while to ring up, so the code outlives its own
            // delivery window once it has been confirmed — but not all day.
            && $this->verified_at->greaterThan(now()->subMinutes(30));
    }

    public function wasOverridden(): bool
    {
        return $this->overridden_by !== null;
    }
}
