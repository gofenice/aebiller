<?php

namespace Tests\Feature;

use App\Enums\LoyaltyTransactionType;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\LoyaltyTier;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The loyalty programme at the till, on the default rules: 1 point per SAR 1,
 * a point is worth SAR 0.01, redeem from 100 points, up to 50% of a bill,
 * and a 50-point welcome bonus.
 */
class LoyaltyBillingTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create();

        LoyaltyTier::factory()->create(['name' => 'Classic', 'min_spend' => 0, 'earn_multiplier' => 1]);
        LoyaltyTier::factory()->reachedAt(1000, 2)->create(['name' => 'Gold']);
    }

    /**
     * Shelf prices include 15% VAT, as the store prices.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function sellableProduct(array $overrides = []): Product
    {
        return Product::factory()->create([
            'current_stock' => 50,
            'selling_price' => 11.50,
            'cost_price' => 8.00,
            'tax_rate' => 15,
            'price_includes_tax' => true,
            ...$overrides,
        ]);
    }

    protected function member(int $extraPoints = 0): Customer
    {
        $loyalty = app(LoyaltyService::class);
        $customer = $loyalty->enrol(['name' => 'Aisha Rahman', 'phone' => '0551234567'], $this->cashier);

        if ($extraPoints > 0) {
            $loyalty->adjust($customer, $extraPoints, 'Test credit', User::factory()->superAdmin()->create());
        }

        return $customer->fresh(['activeCard', 'tier']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function sell(Product $product, float $quantity, array $attributes = []): TestResponse
    {
        return $this->actingAs($this->cashier)->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Card->value,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            ...$attributes,
        ]);
    }

    public function test_scanning_a_loyalty_card_into_the_product_box_finds_the_member(): void
    {
        $customer = $this->member();

        $this->actingAs($this->cashier)
            ->getJson(route('billing.scan', ['code' => $customer->activeCard->number]))
            ->assertOk()
            ->assertJsonPath('match', null)
            ->assertJsonPath('member.id', $customer->id)
            ->assertJsonPath('member.points_balance', 50);
    }

    public function test_a_product_barcode_still_wins_over_a_member_lookup(): void
    {
        $customer = $this->member();
        $product = $this->sellableProduct(['barcode' => '6281007021234']);

        $this->actingAs($this->cashier)
            ->getJson(route('billing.scan', ['code' => '6281007021234']))
            ->assertJsonPath('match.id', $product->id)
            ->assertJsonMissingPath('member');

        $this->assertNotNull($customer);
    }

    public function test_a_replaced_card_is_refused_at_the_till(): void
    {
        $customer = $this->member();
        $oldNumber = $customer->activeCard->number;
        app(LoyaltyService::class)->replaceCard($customer, $this->cashier, lost: true);

        $this->actingAs($this->cashier)
            ->getJson(route('billing.scan', ['code' => $oldNumber]))
            ->assertJsonPath('member', null)
            ->assertJsonPath('member_error', fn (string $error): bool => str_contains($error, 'reported lost'));
    }

    public function test_the_member_box_finds_a_member_by_mobile_number_or_name(): void
    {
        $customer = $this->member();

        $this->actingAs($this->cashier)
            ->getJson(route('billing.member', ['q' => '055 123 4567']))
            ->assertJsonPath('match.id', $customer->id);

        $this->actingAs($this->cashier)
            ->getJson(route('billing.member', ['q' => 'aisha']))
            ->assertJsonPath('match', null)
            ->assertJsonPath('results.0.id', $customer->id);
    }

    public function test_a_new_member_can_sign_up_at_the_till(): void
    {
        $this->actingAs($this->cashier)
            ->postJson(route('billing.member.store'), ['name' => 'Yusuf Ibrahim', 'phone' => '0556789012'])
            ->assertCreated()
            ->assertJsonPath('member.name', 'Yusuf Ibrahim')
            ->assertJsonPath('member.points_balance', 50);

        $this->actingAs($this->cashier)
            ->postJson(route('billing.member.store'), ['name' => 'Someone Else', 'phone' => '055 678 9012'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_a_member_earns_points_on_what_they_pay(): void
    {
        $customer = $this->member();

        // 4 x 11.50 = 46.00 → 46 points on top of the 50 welcome points.
        $this->sell($this->sellableProduct(), 4, ['customer_id' => $customer->id])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();
        $customer->refresh();

        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame('Aisha Rahman', $sale->customer_name);
        $this->assertSame(46, $sale->loyalty_points_earned);
        $this->assertSame(96, $sale->loyalty_balance_after);
        $this->assertSame(96, $customer->points_balance);
        $this->assertSame(1, $customer->visits);
        $this->assertSame(46.00, (float) $customer->lifetime_spend);
        $this->assertNotNull($customer->last_visit_at);
    }

    public function test_a_higher_tier_earns_more_points(): void
    {
        $customer = $this->member();
        $customer->forceFill(['loyalty_tier_id' => LoyaltyTier::where('name', 'Gold')->value('id')])->save();

        $this->sell($this->sellableProduct(), 4, ['customer_id' => $customer->id])->assertSessionHasNoErrors();

        $this->assertSame(92, Sale::firstOrFail()->loyalty_points_earned);
    }

    public function test_redeemed_points_come_off_the_bill_before_vat(): void
    {
        $customer = $this->member(extraPoints: 450); // 500 points = SAR 5.00

        $this->sell($this->sellableProduct(['selling_price' => 100]), 1, [
            'customer_id' => $customer->id,
            'redeem_points' => 500,
        ])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();

        $this->assertSame(5.00, (float) $sale->loyalty_discount);
        $this->assertSame(95.00, (float) $sale->grand_total);
        // VAT is on the 95.00 actually paid: 95 x 15/115.
        $this->assertSame(12.39, (float) $sale->vat_total);
        $this->assertSame(82.61, (float) $sale->subtotal_excl_vat);
        $this->assertSame(500, $sale->loyalty_points_redeemed);
        // 500 - 500 redeemed + 95 earned on the amount paid.
        $this->assertSame(95, $customer->fresh()->points_balance);
        $this->assertDatabaseHas('loyalty_transactions', [
            'sale_id' => $sale->id,
            'type' => LoyaltyTransactionType::Redeem->value,
            'points' => -500,
            'amount' => 5.00,
        ]);
    }

    public function test_points_cannot_pay_for_more_than_half_the_bill(): void
    {
        $customer = $this->member(extraPoints: 950);

        // A 10.00 bill takes at most 5.00 = 500 points.
        $this->sell($this->sellableProduct(['selling_price' => 10]), 1, [
            'customer_id' => $customer->id,
            'redeem_points' => 600,
        ])->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'at most 500 points'));

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(1000, $customer->fresh()->points_balance);
    }

    public function test_points_below_the_minimum_or_above_the_balance_are_refused(): void
    {
        $customer = $this->member(extraPoints: 100); // 150 points
        $product = $this->sellableProduct(['selling_price' => 100]);

        $this->sell($product, 1, ['customer_id' => $customer->id, 'redeem_points' => 60])->assertSessionHas('error');
        $this->sell($product, 1, ['customer_id' => $customer->id, 'redeem_points' => 400])->assertSessionHas('error');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(150, $customer->fresh()->points_balance);
    }

    public function test_voiding_a_bill_takes_back_the_earned_points_and_returns_the_redeemed_ones(): void
    {
        $customer = $this->member(extraPoints: 450); // 500 points

        $this->sell($this->sellableProduct(['selling_price' => 100]), 1, [
            'customer_id' => $customer->id,
            'redeem_points' => 200,
        ])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();
        $this->assertSame(398, $customer->fresh()->points_balance); // 500 - 200 + 98

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('sales.destroy', $sale))
            ->assertSessionHasNoErrors();

        $customer->refresh();

        $this->assertSame(500, $customer->points_balance);
        $this->assertSame(0, $customer->visits);
        $this->assertSame(0.0, (float) $customer->lifetime_spend);
        $this->assertDatabaseHas('loyalty_transactions', ['sale_id' => $sale->id, 'type' => LoyaltyTransactionType::EarnReversal->value, 'points' => -98]);
        $this->assertDatabaseHas('loyalty_transactions', ['sale_id' => $sale->id, 'type' => LoyaltyTransactionType::RedeemRefund->value, 'points' => 200]);
    }

    public function test_spend_moves_a_member_up_a_tier_and_a_void_moves_them_back(): void
    {
        $customer = $this->member();

        $this->sell($this->sellableProduct(['selling_price' => 250]), 4, ['customer_id' => $customer->id])->assertSessionHasNoErrors();

        $this->assertSame('Gold', $customer->fresh()->tier->name);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('sales.destroy', Sale::firstOrFail()))
            ->assertSessionHasNoErrors();

        $this->assertSame('Classic', $customer->fresh()->tier->name);
    }

    public function test_a_membership_on_hold_cannot_be_used(): void
    {
        $customer = $this->member();
        $customer->update(['is_active' => false]);

        $this->sell($this->sellableProduct(), 1, ['customer_id' => $customer->id])->assertSessionHas('error');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_the_receipt_shows_the_points(): void
    {
        $customer = $this->member();

        $this->sell($this->sellableProduct(), 4, ['customer_id' => $customer->id])->assertSessionHasNoErrors();

        $this->actingAs($this->cashier)
            ->get(route('sales.show', Sale::firstOrFail()))
            ->assertOk()
            ->assertSee('Points earned on this bill')
            ->assertSee($customer->activeCard->maskedNumber());
    }

    public function test_a_bill_without_a_member_is_unchanged(): void
    {
        $this->sell($this->sellableProduct(), 4)->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();

        $this->assertNull($sale->customer_id);
        $this->assertSame(0, $sale->loyalty_points_earned);
        $this->assertSame(46.00, (float) $sale->grand_total);
        $this->assertDatabaseCount('loyalty_transactions', 0);
    }
}
