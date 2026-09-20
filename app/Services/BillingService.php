<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\LoyaltyRedemptionOtp;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a till basket into a posted bill: prices the lines, splits out VAT,
 * writes the sale and takes the goods out of stock. When a loyalty member is
 * attached, their redeemed points come off the bill and the earned points are
 * credited in the same transaction.
 */
class BillingService
{
    public function __construct(
        protected InventoryService $inventory,
        protected LoyaltyService $loyalty,
        protected WhatsAppGateway $whatsapp,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createSale(array $attributes, array $lines, User $cashier): Sale
    {
        if ($lines === []) {
            throw new RuntimeException('The basket is empty.');
        }

        return DB::transaction(function () use ($attributes, $lines, $cashier): Sale {
            $billDiscount = (float) ($attributes['bill_discount'] ?? 0);
            $priced = $this->priceBasket($lines, $billDiscount);

            $customer = filled($attributes['customer_id'] ?? null)
                ? Customer::with('tier')->lockForUpdate()->find($attributes['customer_id'])
                : null;

            if ($customer !== null && ! $customer->is_active) {
                throw new RuntimeException("{$customer->name}'s membership is on hold — take the sale without the card.");
            }

            $pointsRedeemed = $customer !== null ? (int) ($attributes['redeem_points'] ?? 0) : 0;
            $approval = null;

            if ($pointsRedeemed > 0) {
                // Inside the transaction and locked, so the same approval
                // cannot settle two bills at once.
                $approval = $this->approvalFor($customer, $pointsRedeemed, $attributes['redemption_otp_id'] ?? null);

                $totals = $priced['totals'];
                $payable = round($totals['items_gross'] - $totals['line_discount_total'] - $totals['bill_discount'], 2);
                $loyaltyDiscount = $this->loyalty->redemptionDiscount($customer, $pointsRedeemed, $payable);

                // Price the same lines again with the points taken off too, so
                // VAT is worked out on what the customer actually pays.
                $priced = $this->priceBasket(array_map(fn (array $line): array => [
                    'product' => $line['product'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => $line['discount_percent'],
                ], $priced['lines']), $billDiscount, $loyaltyDiscount);
            }

            $paymentMethod = $attributes['payment_method'] instanceof PaymentMethod
                ? $attributes['payment_method']
                : PaymentMethod::from($attributes['payment_method']);

            $grandTotal = $priced['totals']['grand_total'];

            if ($paymentMethod->isCredit() && $customer === null) {
                // Without a member there is nobody to chase for the money.
                throw new RuntimeException('Credit needs a loyalty member on the bill — attach the customer first.');
            }

            $amountPaid = match (true) {
                $paymentMethod->isCredit() => min((float) ($attributes['amount_paid'] ?? 0), $grandTotal),
                $paymentMethod->needsTendering() => (float) ($attributes['amount_paid'] ?? $grandTotal),
                default => $grandTotal,
            };

            if ($paymentMethod->needsTendering() && $amountPaid + 0.001 < $grandTotal) {
                throw new RuntimeException(sprintf(
                    'Cash tendered (%s) is less than the bill total (%s).',
                    number_format($amountPaid, 2),
                    number_format($grandTotal, 2),
                ));
            }

            $outstanding = $paymentMethod->isCredit() ? round(max($grandTotal - $amountPaid, 0), 2) : 0.0;

            $sale = Sale::create([
                'invoice_no' => $this->inventory->nextReference(Sale::class, 'INV', 'invoice_no'),
                'status' => SaleStatus::Completed,
                'customer_id' => $customer?->id,
                'customer_name' => ($attributes['customer_name'] ?? null) ?: $customer?->name,
                'customer_phone' => ($attributes['customer_phone'] ?? null) ?: $customer?->phone,
                'customer_vat_number' => $attributes['customer_vat_number'] ?? null,
                ...$priced['totals'],
                'payment_method' => $paymentMethod,
                'amount_paid' => round($amountPaid, 2),
                'change_due' => $paymentMethod->isCredit() ? 0 : round(max($amountPaid - $grandTotal, 0), 2),
                'amount_outstanding' => $outstanding,
                'settled_at' => $paymentMethod->isCredit() && $outstanding <= 0 ? now() : null,
                'notes' => $attributes['notes'] ?? null,
                'cashier_id' => $cashier->id,
                'sold_at' => $attributes['sold_at'] ?? now(),
            ]);

            foreach ($priced['lines'] as $line) {
                $product = $line['product'];

                $sale->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->display_name,
                    'sku' => $product->sku,
                    'unit_code' => $product->unit?->code,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => $line['discount_percent'],
                    'discount_amount' => $line['discount_amount'],
                    'vat_rate' => $line['vat_rate'],
                    'line_subtotal' => $line['line_subtotal'],
                    'line_vat' => $line['line_vat'],
                    'line_total' => $line['line_total'],
                    'unit_cost' => $line['unit_cost'],
                ]);

                $this->inventory->issueStock(
                    product: $product,
                    quantity: $line['quantity'],
                    type: MovementType::Sale,
                    user: $cashier,
                    source: $sale,
                    reference: $sale->invoice_no,
                    movedAt: Carbon::parse($sale->sold_at),
                );
            }

            if ($customer !== null) {
                $this->loyalty->recordSale($sale, $customer, $pointsRedeemed, $cashier);
            }

            // Spent: this approval can never authorise another redemption.
            $approval?->forceFill(['consumed_at' => now(), 'sale_id' => $sale->id])->save();

            return $sale->fresh(['items', 'cashier']);
        });
    }

    /**
     * The approval that lets this member's points be spent.
     *
     * Where WhatsApp is set up, a member confirms their own redemption with a
     * code, so a found card is worthless on its own. Where it is not set up
     * there is no way to send a code, and redemption carries on as it did
     * before — a code that cannot be delivered must not close the till.
     */
    protected function approvalFor(Customer $customer, int $points, mixed $otpId): ?LoyaltyRedemptionOtp
    {
        if (! $this->whatsapp->enabled()) {
            return null;
        }

        $otp = filled($otpId)
            ? LoyaltyRedemptionOtp::where('customer_id', $customer->id)->lockForUpdate()->find($otpId)
            : null;

        if ($otp === null || ! $otp->isApprovalFor($customer, $points)) {
            throw new RuntimeException(
                "{$customer->name} needs to confirm this redemption with the code sent to their WhatsApp."
            );
        }

        return $otp;
    }

    /**
     * Cancel a posted bill and put the goods back on the shelf.
     */
    public function voidSale(Sale $sale, User $user, ?string $reason = null): void
    {
        if ($sale->isVoided()) {
            throw new RuntimeException("Bill {$sale->invoice_no} has already been voided.");
        }

        DB::transaction(function () use ($sale, $user, $reason): void {
            $sale->loadMissing('items');

            foreach ($sale->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                $product = Product::lockForUpdate()->find($item->product_id);

                if ($product === null) {
                    continue;
                }

                $this->inventory->returnStock(
                    product: $product,
                    quantity: (float) $item->quantity,
                    type: MovementType::SalesReturn,
                    user: $user,
                    source: $sale,
                    reference: $sale->invoice_no,
                    notes: "Void of {$sale->invoice_no}",
                );
            }

            $sale->update([
                'status' => SaleStatus::Voided,
                'voided_by' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
                // A cancelled bill is not a debt, whatever was owed on it.
                'amount_outstanding' => 0,
            ]);

            $this->loyalty->reverseSale($sale, $user);
        });
    }

    /**
     * Take money against a credit bill, in full or in part.
     *
     * @param  array{amount: float|string, payment_method?: PaymentMethod|string, reference?: ?string, notes?: ?string, received_at?: mixed}  $attributes
     */
    public function settleCredit(Sale $sale, array $attributes, User $user): CreditPayment
    {
        return DB::transaction(function () use ($sale, $attributes, $user): CreditPayment {
            // Locked, so two cashiers taking the same payment cannot both
            // knock it off the balance.
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);

            if ($sale->isVoided()) {
                throw new RuntimeException("Bill {$sale->invoice_no} was voided — there is nothing to collect.");
            }

            if ($sale->isSettled()) {
                throw new RuntimeException("Bill {$sale->invoice_no} is already paid in full.");
            }

            $amount = round((float) $attributes['amount'], 2);

            if ($amount <= 0) {
                throw new RuntimeException('Enter how much the customer is paying.');
            }

            $outstanding = (float) $sale->amount_outstanding;

            if ($amount - 0.001 > $outstanding) {
                throw new RuntimeException(sprintf(
                    'That is more than the %s still owed on this bill.',
                    number_format($outstanding, 2),
                ));
            }

            $method = $attributes['payment_method'] ?? PaymentMethod::Cash;

            if (! $method instanceof PaymentMethod) {
                $method = PaymentMethod::from($method);
            }

            if ($method->isCredit()) {
                throw new RuntimeException('Credit cannot pay off credit — record how the money arrived.');
            }

            $payment = $sale->creditPayments()->create([
                'customer_id' => $sale->customer_id,
                'amount' => $amount,
                'payment_method' => $method,
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'received_by' => $user->id,
                'received_at' => $attributes['received_at'] ?? now(),
            ]);

            $remaining = round($outstanding - $amount, 2);

            $sale->update([
                'amount_paid' => round((float) $sale->amount_paid + $amount, 2),
                'amount_outstanding' => $remaining,
                'settled_at' => $remaining <= 0 ? now() : null,
            ]);

            return $payment;
        });
    }

    /**
     * Price every line, split VAT out of the shelf price and spread any
     * bill-level discount — the cashier's and the redeemed points' — across
     * the lines so the VAT stays correct.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function priceBasket(array $lines, float $billDiscount = 0, float $loyaltyDiscount = 0): array
    {
        $priced = [];
        $itemsGross = 0.0;
        $lineDiscountTotal = 0.0;
        $afterLineDiscount = 0.0;

        foreach ($lines as $line) {
            $product = $line['product'] ?? Product::with('unit')->lockForUpdate()->findOrFail($line['product_id']);
            $quantity = round((float) $line['quantity'], 3);

            if ($quantity <= 0) {
                continue;
            }

            if (! $product->is_active) {
                throw new RuntimeException("{$product->name} is not active and cannot be sold.");
            }

            if ($quantity > (float) $product->current_stock) {
                throw new RuntimeException(sprintf(
                    '%s has only %s %s in stock — the basket asks for %s.',
                    $product->name,
                    rtrim(rtrim(number_format((float) $product->current_stock, 3, '.', ''), '0'), '.'),
                    $product->unit?->code ?? 'units',
                    rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.'),
                ));
            }

            $unitPrice = isset($line['unit_price']) && (float) $line['unit_price'] > 0
                ? round((float) $line['unit_price'], 2)
                : (float) $product->selling_price;

            $discountPercent = min(max((float) ($line['discount_percent'] ?? 0), 0), 100);
            $gross = round($quantity * $unitPrice, 2);
            $discountAmount = round($gross * $discountPercent / 100, 2);
            $net = round($gross - $discountAmount, 2);

            $itemsGross += $gross;
            $lineDiscountTotal += $discountAmount;
            $afterLineDiscount += $net;

            $priced[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'net' => $net,
                'vat_rate' => (float) $product->tax_rate,
                'unit_cost' => (float) $product->cost_price,
            ];
        }

        if ($priced === []) {
            throw new RuntimeException('The basket is empty.');
        }

        $billDiscount = round(min(max($billDiscount, 0), $afterLineDiscount), 2);
        // Redeemed points come off whatever is left after the cashier's discount.
        $loyaltyDiscount = round(min(max($loyaltyDiscount, 0), $afterLineDiscount - $billDiscount), 2);
        $discount = round($billDiscount + $loyaltyDiscount, 2);
        $distributed = 0.0;
        $lastIndex = count($priced) - 1;

        foreach ($priced as $index => &$line) {
            // The final line absorbs the rounding remainder so the discount
            // spread always adds back up to the figure the cashier entered.
            $share = $index === $lastIndex
                ? round($discount - $distributed, 2)
                : ($afterLineDiscount > 0 ? round($discount * $line['net'] / $afterLineDiscount, 2) : 0.0);

            $distributed += $share;
            $net = round($line['net'] - $share, 2);
            $rate = $line['vat_rate'];

            if ($line['product']->price_includes_tax) {
                $vat = round($net * $rate / (100 + $rate), 2);
                $subtotal = round($net - $vat, 2);
                $total = $net;
            } else {
                $subtotal = $net;
                $vat = round($net * $rate / 100, 2);
                $total = round($net + $vat, 2);
            }

            $line['line_subtotal'] = $subtotal;
            $line['line_vat'] = $vat;
            $line['line_total'] = $total;
        }
        unset($line);

        $totals = [
            'items_gross' => round($itemsGross, 2),
            'line_discount_total' => round($lineDiscountTotal, 2),
            'bill_discount' => $billDiscount,
            'loyalty_discount' => $loyaltyDiscount,
            'subtotal_excl_vat' => round(array_sum(array_column($priced, 'line_subtotal')), 2),
            'vat_total' => round(array_sum(array_column($priced, 'line_vat')), 2),
            'grand_total' => round(array_sum(array_column($priced, 'line_total')), 2),
            'cost_total' => round(array_sum(array_map(
                fn (array $line): float => $line['quantity'] * $line['unit_cost'],
                $priced,
            )), 2),
        ];

        return ['lines' => $priced, 'totals' => $totals];
    }
}
