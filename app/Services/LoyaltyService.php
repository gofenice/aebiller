<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\LoyaltyCard;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single writer for loyalty points. Every change to a member's balance
 * goes through here, so the ledger and the running balance never disagree.
 *
 * Credits remember how much of them is still unspent. Debits use up the
 * credits that expire soonest first, so a member never loses points to
 * expiry while holding newer ones they could have spent instead.
 */
class LoyaltyService
{
    /**
     * Card numbers are EAN-13 codes in GS1's 2x "in-store use" range, so they
     * never clash with a manufacturer's barcode.
     */
    public const CARD_PREFIX = '29';

    public function __construct(protected BarcodeGenerator $barcodes) {}

    public function settings(): LoyaltySetting
    {
        return LoyaltySetting::current();
    }

    /**
     * Sign up a new member, issue their card and credit the welcome bonus.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function enrol(array $attributes, User $user): Customer
    {
        return DB::transaction(function () use ($attributes, $user): Customer {
            $customer = Customer::create([
                ...$attributes,
                'loyalty_tier_id' => LoyaltyTier::entry()?->id,
                'enrolled_by' => $user->id,
            ]);

            $this->issueCard($customer, $user);

            $settings = $this->settings();

            if ($settings->is_enabled && $settings->welcome_bonus > 0) {
                $this->post($customer, LoyaltyTransactionType::WelcomeBonus, $settings->welcome_bonus,
                    user: $user, description: "Welcome to {$settings->program_name}");
            }

            return $customer->fresh(['tier', 'activeCard']);
        });
    }

    /**
     * Issue a new card, retiring whatever card the member held before.
     */
    public function issueCard(
        Customer $customer,
        ?User $user = null,
        CardStatus $retireAs = CardStatus::Replaced,
        ?string $reason = null,
    ): LoyaltyCard {
        return DB::transaction(function () use ($customer, $user, $retireAs, $reason): LoyaltyCard {
            LoyaltyCard::query()
                ->where('customer_id', $customer->id)
                ->where('status', CardStatus::Active)
                ->update([
                    'status' => $retireAs,
                    'retired_at' => now(),
                    'retired_reason' => $reason,
                ]);

            $card = LoyaltyCard::create([
                'customer_id' => $customer->id,
                'number' => $this->nextCardNumber(),
                'status' => CardStatus::Active,
                'issued_at' => now(),
                'issued_by' => $user?->id,
            ]);

            $customer->setRelation('activeCard', $card);

            return $card;
        });
    }

    /**
     * A lost or damaged card is swapped for a new number. The points belong
     * to the member, not to the plastic, so they carry straight over.
     */
    public function replaceCard(Customer $customer, User $user, bool $lost, ?string $reason = null): LoyaltyCard
    {
        return $this->issueCard(
            $customer,
            $user,
            $lost ? CardStatus::Lost : CardStatus::Replaced,
            $reason ?: ($lost ? 'Reported lost' : 'Card replaced'),
        );
    }

    public function nextCardNumber(): string
    {
        $last = LoyaltyCard::query()->where('number', 'like', self::CARD_PREFIX.'%')->max('number');
        $sequence = $last !== null ? ((int) substr((string) $last, 2, 10)) + 1 : 1;

        // Shelf labels printed in the store share the 2x range, so a number
        // that is already on a product is skipped.
        do {
            $body = self::CARD_PREFIX.str_pad((string) $sequence++, 10, '0', STR_PAD_LEFT);
            $number = $body.$this->barcodes->checkDigit($body);
        } while (Product::withTrashed()->where('barcode', $number)->exists());

        return $number;
    }

    /**
     * Resolve a scanned card or a typed mobile number to a member the till
     * can use. A retired card or a member on hold comes back with the reason.
     *
     * @return array{customer: Customer|null, error: string|null}
     */
    public function findMember(string $code): array
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';

        if (strlen($digits) === 13) {
            $card = LoyaltyCard::with('customer.tier')->where('number', $digits)->first();

            if ($card !== null) {
                if (! $card->isActive()) {
                    return ['customer' => null, 'error' => sprintf(
                        'Card %s was %s on %s. Ask for the new card, or find the member by mobile number.',
                        $card->maskedNumber(),
                        $card->status === CardStatus::Lost ? 'reported lost' : 'replaced',
                        $card->retired_at?->format('d M Y') ?? 'an earlier date',
                    )];
                }

                return $this->usable($card->customer);
            }
        }

        if (strlen($digits) >= 9) {
            $customer = Customer::with('tier')->where('phone', Customer::normalizePhone($digits))->first();

            if ($customer !== null) {
                return $this->usable($customer);
            }
        }

        return ['customer' => null, 'error' => null];
    }

    /**
     * @return array{customer: Customer|null, error: string|null}
     */
    protected function usable(Customer $customer): array
    {
        if (! $customer->is_active) {
            return ['customer' => null, 'error' => "{$customer->name}'s membership is on hold, so points cannot be earned or redeemed."];
        }

        return ['customer' => $customer, 'error' => null];
    }

    /**
     * What the till shows once a member is attached to the bill.
     *
     * @return array{id: int, name: string, phone: string, card: string|null, tier: string|null, multiplier: float, points_balance: int, points_value: float, url: string}
     */
    public function presentForTill(Customer $customer): array
    {
        $customer->loadMissing(['tier', 'activeCard']);

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->formattedPhone(),
            'card' => $customer->activeCard?->maskedNumber(),
            'tier' => $customer->tier?->name,
            'multiplier' => (float) ($customer->tier?->earn_multiplier ?? 1),
            'points_balance' => $customer->points_balance,
            'points_value' => $customer->pointsValue($this->settings()),
            'url' => route('customers.show', $customer),
        ];
    }

    /**
     * Points earned on a spend, at the member's tier multiplier.
     */
    public function pointsFor(Customer $customer, float $amount): int
    {
        $settings = $this->settings();

        if (! $settings->is_enabled || ! $customer->is_active || $amount <= 0) {
            return 0;
        }

        $multiplier = (float) ($customer->tier?->earn_multiplier ?? 1);

        // Rounded before flooring so 45.999999 from float arithmetic counts as 46.
        return (int) floor(round($amount * (float) $settings->points_per_currency * $multiplier, 4));
    }

    /**
     * The most points a bill can take: never more than the member holds, nor
     * more than the programme's share of the bill.
     */
    public function redeemableFor(Customer $customer, float $payable): int
    {
        $settings = $this->settings();
        $pointValue = (float) $settings->point_value;

        if (! $settings->is_enabled || ! $customer->is_active || $pointValue <= 0 || $payable <= 0) {
            return 0;
        }

        $cap = $payable * (float) $settings->max_redeem_percent / 100;
        $byCap = (int) floor(round($cap / $pointValue, 4));

        return max(0, min($customer->points_balance, $byCap));
    }

    /**
     * Check the points the cashier asked to redeem and return what they take
     * off the bill.
     */
    public function redemptionDiscount(Customer $customer, int $points, float $payable): float
    {
        if ($points <= 0) {
            return 0.0;
        }

        $settings = $this->settings();

        if (! $settings->is_enabled) {
            throw new RuntimeException('The loyalty programme is switched off, so points cannot be redeemed.');
        }

        if (! $customer->is_active) {
            throw new RuntimeException("{$customer->name}'s membership is on hold, so points cannot be redeemed.");
        }

        if ($points < $settings->min_redeem_points) {
            throw new RuntimeException(sprintf('Points are redeemed %s at a time or more.', number_format($settings->min_redeem_points)));
        }

        if ($points > $customer->points_balance) {
            throw new RuntimeException(sprintf(
                '%s has %s points — the bill asks for %s.',
                $customer->name,
                number_format($customer->points_balance),
                number_format($points),
            ));
        }

        $limit = $this->redeemableFor($customer, $payable);

        if ($points > $limit) {
            throw new RuntimeException(sprintf(
                'Points can pay for up to %s%% of the bill — at most %s points on this one.',
                rtrim(rtrim(number_format((float) $settings->max_redeem_percent, 2), '0'), '.'),
                number_format($limit),
            ));
        }

        return $settings->pointsWorth($points);
    }

    /**
     * Post the loyalty side of a bill that has just been completed: take the
     * redeemed points, credit the earned ones and bring the member's figures
     * up to date.
     */
    public function recordSale(Sale $sale, Customer $customer, int $pointsRedeemed, User $cashier): void
    {
        DB::transaction(function () use ($sale, $customer, $pointsRedeemed, $cashier): void {
            if ($pointsRedeemed > 0) {
                $this->post($customer, LoyaltyTransactionType::Redeem, -$pointsRedeemed,
                    user: $cashier, sale: $sale, amount: (float) $sale->loyalty_discount,
                    description: "Redeemed on {$sale->invoice_no}");
            }

            $earned = $this->pointsFor($customer, (float) $sale->grand_total);

            if ($earned > 0) {
                $this->post($customer, LoyaltyTransactionType::Earn, $earned,
                    user: $cashier, sale: $sale, amount: (float) $sale->grand_total,
                    description: "Bill {$sale->invoice_no}");
            }

            $customer->forceFill([
                'lifetime_spend' => round((float) $customer->lifetime_spend + (float) $sale->grand_total, 2),
                'visits' => $customer->visits + 1,
                'last_visit_at' => $sale->sold_at,
            ])->save();

            $this->refreshTier($customer);

            $sale->update([
                'loyalty_points_earned' => $earned,
                'loyalty_points_redeemed' => $pointsRedeemed,
                'loyalty_balance_after' => $customer->points_balance,
            ]);
        });
    }

    /**
     * Undo the loyalty side of a voided bill: the points it earned are taken
     * back and the points it used are returned.
     */
    public function reverseSale(Sale $sale, User $user): void
    {
        $customer = $sale->customer;

        if ($customer === null) {
            return;
        }

        DB::transaction(function () use ($sale, $customer, $user): void {
            if ($sale->loyalty_points_earned > 0) {
                $this->post($customer, LoyaltyTransactionType::EarnReversal, -$sale->loyalty_points_earned,
                    user: $user, sale: $sale, amount: (float) $sale->grand_total,
                    description: "Void of {$sale->invoice_no}");
            }

            if ($sale->loyalty_points_redeemed > 0) {
                $this->post($customer, LoyaltyTransactionType::RedeemRefund, $sale->loyalty_points_redeemed,
                    user: $user, sale: $sale, amount: (float) $sale->loyalty_discount,
                    description: "Returned — void of {$sale->invoice_no}");
            }

            $customer->forceFill([
                'lifetime_spend' => round(max((float) $customer->lifetime_spend - (float) $sale->grand_total, 0), 2),
                'visits' => max($customer->visits - 1, 0),
            ])->save();

            $this->refreshTier($customer);
        });
    }

    /**
     * A goodwill credit or a correction, entered by a super admin with a reason.
     */
    public function adjust(Customer $customer, int $points, string $reason, User $user): LoyaltyTransaction
    {
        if ($points === 0) {
            throw new RuntimeException('Enter the number of points to add, or a negative number to take away.');
        }

        $customer->refresh();

        if ($points < 0 && -$points > $customer->points_balance) {
            throw new RuntimeException(sprintf(
                '%s holds %s points, so %s cannot be taken away.',
                $customer->name,
                number_format($customer->points_balance),
                number_format(-$points),
            ));
        }

        return $this->post($customer, LoyaltyTransactionType::Adjustment, $points, user: $user, description: $reason);
    }

    /**
     * Lapse every credit whose expiry date has passed. Runs nightly.
     *
     * @return array{members: int, points: int}
     */
    public function expirePoints(?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();
        $members = 0;
        $total = 0;

        $customerIds = LoyaltyTransaction::query()->expiringBy($asOf)->distinct()->pluck('customer_id');

        foreach ($customerIds as $customerId) {
            $expired = DB::transaction(function () use ($customerId, $asOf): int {
                /** @var Collection<int, LoyaltyTransaction> $credits */
                $credits = LoyaltyTransaction::query()
                    ->where('customer_id', $customerId)
                    ->expiringBy($asOf)
                    ->lockForUpdate()
                    ->get();

                $points = (int) $credits->sum('points_remaining');

                if ($points <= 0) {
                    return 0;
                }

                LoyaltyTransaction::whereKey($credits->modelKeys())->update(['points_remaining' => 0]);

                $this->post(Customer::findOrFail($customerId), LoyaltyTransactionType::Expiry, -$points,
                    description: 'Unused points earned since '.$credits->min('created_at')->format('d M Y'),
                    consume: false);

                return $points;
            });

            if ($expired > 0) {
                $members++;
                $total += $expired;
            }
        }

        return ['members' => $members, 'points' => $total];
    }

    /**
     * Credit the birthday bonus to every member whose birthday is today, once a year.
     */
    public function awardBirthdayBonuses(?CarbonInterface $today = null): int
    {
        $today = Carbon::parse($today ?? today());
        $settings = $this->settings();

        if (! $settings->is_enabled || $settings->birthday_bonus <= 0) {
            return 0;
        }

        $customers = Customer::query()
            ->active()
            ->whereNotNull('birth_date')
            ->where(function ($query) use ($today): void {
                $query->where(fn ($query) => $query->whereMonth('birth_date', $today->month)->whereDay('birth_date', $today->day));

                // Members born on 29 February celebrate on the 28th in other years.
                if ($today->month === 2 && $today->day === 28 && ! $today->isLeapYear()) {
                    $query->orWhere(fn ($query) => $query->whereMonth('birth_date', 2)->whereDay('birth_date', 29));
                }
            })
            ->whereDoesntHave('transactions', fn ($query) => $query
                ->where('type', LoyaltyTransactionType::BirthdayBonus)
                ->whereYear('created_at', $today->year))
            ->get();

        foreach ($customers as $customer) {
            $this->post($customer, LoyaltyTransactionType::BirthdayBonus, $settings->birthday_bonus,
                description: "Happy birthday from {$settings->program_name}");
        }

        return $customers->count();
    }

    /**
     * Spend on completed bills inside the tier window.
     */
    public function qualifyingSpend(Customer $customer): float
    {
        return (float) $customer->sales()
            ->completed()
            ->where('sold_at', '>=', now()->subMonths($this->settings()->tier_window_months))
            ->sum('grand_total');
    }

    /**
     * Move the member to the tier their recent spend earns — up or down.
     */
    public function refreshTier(Customer $customer): bool
    {
        $tier = LoyaltyTier::forSpend($this->qualifyingSpend($customer));

        if ($tier === null || $tier->id === $customer->loyalty_tier_id) {
            return false;
        }

        $customer->forceFill(['loyalty_tier_id' => $tier->id])->save();
        $customer->setRelation('tier', $tier);

        return true;
    }

    /**
     * Re-tier every member, e.g. after the thresholds change or overnight as
     * old spend falls out of the window.
     */
    public function refreshAllTiers(): int
    {
        $changed = 0;

        Customer::query()->chunkById(200, function (Collection $customers) use (&$changed): void {
            foreach ($customers as $customer) {
                if ($this->refreshTier($customer)) {
                    $changed++;
                }
            }
        });

        return $changed;
    }

    /**
     * Figures for the member's profile page.
     *
     * @return array{qualifying_spend: float, next_tier: array{tier: LoyaltyTier, remaining: float}|null, expiring_points: int, next_expiry: Carbon|null, issued: int, redeemed: int, average_basket: float}
     */
    public function memberSummary(Customer $customer): array
    {
        $spend = $this->qualifyingSpend($customer);
        $expiring = $customer->transactions()->expiringBy(now()->addDays(30));
        $nextExpiry = (clone $expiring)->min('expires_at');

        $redeemed = -1 * (int) $customer->transactions()->where('type', LoyaltyTransactionType::Redeem)->sum('points')
            - (int) $customer->transactions()->where('type', LoyaltyTransactionType::RedeemRefund)->sum('points');

        return [
            'qualifying_spend' => $spend,
            'next_tier' => LoyaltyTier::nextAfter($customer->tier, $spend),
            'expiring_points' => (int) (clone $expiring)->sum('points_remaining'),
            'next_expiry' => $nextExpiry !== null ? Carbon::parse($nextExpiry) : null,
            'issued' => (int) $customer->transactions()->whereIn('type', LoyaltyTransactionType::issuing())->sum('points'),
            'redeemed' => max($redeemed, 0),
            'average_basket' => $customer->visits > 0 ? round((float) $customer->lifetime_spend / $customer->visits, 2) : 0.0,
        ];
    }

    /**
     * Write one ledger line and move the balance with it. Positive points are
     * a credit, negative a debit.
     */
    protected function post(
        Customer $customer,
        LoyaltyTransactionType $type,
        int $points,
        ?User $user = null,
        ?Sale $sale = null,
        ?float $amount = null,
        ?string $description = null,
        bool $consume = true,
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($customer, $type, $points, $user, $sale, $amount, $description, $consume): LoyaltyTransaction {
            $balance = (int) Customer::whereKey($customer->id)->lockForUpdate()->value('points_balance');
            $after = $balance + $points;

            if ($points < 0 && $consume) {
                $this->consume($customer, -$points);
            }

            // A member in the red — a voided bill took back points they had
            // already spent — clears that first. Only the part of a credit
            // that lifts the balance above zero is left to spend or expire.
            $remaining = $points > 0 ? max(0, min($points, $after)) : 0;

            $transaction = LoyaltyTransaction::create([
                'customer_id' => $customer->id,
                'type' => $type,
                'points' => $points,
                'balance_after' => $after,
                'points_remaining' => $remaining,
                'expires_at' => $remaining > 0 ? $this->settings()->expiryFrom(now()) : null,
                'sale_id' => $sale?->id,
                'amount' => $amount,
                'description' => $description,
                'user_id' => $user?->id,
            ]);

            Customer::whereKey($customer->id)->update(['points_balance' => $after]);
            $customer->forceFill(['points_balance' => $after])->syncOriginalAttribute('points_balance');

            return $transaction;
        });
    }

    /**
     * Use up unspent credit, soonest-expiring first; credit that never
     * expires is used last.
     */
    protected function consume(Customer $customer, int $points): void
    {
        $credits = LoyaltyTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('points_remaining', '>', 0)
            ->orderByRaw('case when expires_at is null then 1 else 0 end')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($credits as $credit) {
            if ($points <= 0) {
                break;
            }

            $used = min($points, $credit->points_remaining);
            $credit->update(['points_remaining' => $credit->points_remaining - $used]);
            $points -= $used;
        }
    }
}
