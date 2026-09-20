<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyRedemptionOtp;
use App\Models\LoyaltySetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\BillingService;
use Database\Factories\LoyaltyRedemptionOtpFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Points are the member's money, so the member authorises them being spent.
 * A card on its own is not enough: the code goes to their handset.
 */
class RedemptionOtpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Credentials live on the store, so a request re-applying the store's
     * settings keeps them rather than wiping them.
     */
    protected function enableWhatsApp(): void
    {
        $this->store->update([
            'whatsapp_token' => 'test-token',
            'whatsapp_phone_number_id' => '1234567890',
        ]);

        $this->useStore($this->store->fresh());
    }

    protected function member(int $points = 5000, ?string $phone = null): Customer
    {
        // A shop cannot hold the same mobile twice, so each member gets their
        // own unless the test names one.
        $customer = Customer::factory()->create([
            'phone' => $phone ?? fake()->unique()->numerify('05########'),
        ]);

        $customer->forceFill(['points_balance' => $points])->save();

        return $customer->fresh();
    }

    /**
     * An ordinary member of staff. Admin is the factory default: it passes
     * run-till and manage-customers, but not the owner-only gate, which is
     * what makes the override test mean something.
     */
    protected function cashier(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    protected function owner(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * A basket that can actually be sold, so a refusal is the OTP's doing.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function basket(): array
    {
        $product = Product::factory()->create(['selling_price' => 500, 'current_stock' => 50, 'tax_rate' => 0]);

        return [['product_id' => $product->id, 'quantity' => 1]];
    }

    public function test_without_whatsapp_configured_redemption_works_as_before(): void
    {
        // No token: there is no way to send a code, so demanding one would
        // simply stop the till.
        LoyaltySetting::current()->update(['is_enabled' => true, 'min_redeem_points' => 100, 'point_value' => 0.01]);
        $customer = $this->member();

        $sale = app(BillingService::class)->createSale(
            ['payment_method' => 'card', 'customer_id' => $customer->id, 'redeem_points' => 100],
            $this->basket(),
            $this->cashier(),
        );

        $this->assertSame(100, $sale->loyalty_points_redeemed);
    }

    public function test_with_whatsapp_configured_redemption_is_refused_without_a_code(): void
    {
        $this->enableWhatsApp();
        LoyaltySetting::current()->update(['is_enabled' => true, 'min_redeem_points' => 100, 'point_value' => 0.01]);
        $customer = $this->member();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/confirm this redemption/i');

        app(BillingService::class)->createSale(
            ['payment_method' => 'card', 'customer_id' => $customer->id, 'redeem_points' => 100],
            $this->basket(),
            $this->cashier(),
        );
    }

    public function test_a_verified_code_lets_the_redemption_through_and_is_then_spent(): void
    {
        $this->enableWhatsApp();
        LoyaltySetting::current()->update(['is_enabled' => true, 'min_redeem_points' => 100, 'point_value' => 0.01]);
        $customer = $this->member();

        $otp = LoyaltyRedemptionOtp::factory()->verified()->forPoints(100)->create(['customer_id' => $customer->id]);

        $sale = app(BillingService::class)->createSale(
            ['payment_method' => 'card', 'customer_id' => $customer->id, 'redeem_points' => 100, 'redemption_otp_id' => $otp->id],
            $this->basket(),
            $this->cashier(),
        );

        $this->assertSame(100, $sale->loyalty_points_redeemed);

        $otp->refresh();
        $this->assertNotNull($otp->consumed_at);
        $this->assertSame($sale->id, $otp->sale_id);
    }

    public function test_a_code_cannot_settle_more_points_than_it_was_approved_for(): void
    {
        $this->enableWhatsApp();
        LoyaltySetting::current()->update(['is_enabled' => true, 'min_redeem_points' => 100, 'point_value' => 0.01]);
        $customer = $this->member();

        // Approved for 100; the bill asks for 5,000.
        $otp = LoyaltyRedemptionOtp::factory()->verified()->forPoints(100)->create(['customer_id' => $customer->id]);

        $this->expectException(RuntimeException::class);

        app(BillingService::class)->createSale(
            ['payment_method' => 'card', 'customer_id' => $customer->id, 'redeem_points' => 5000, 'redemption_otp_id' => $otp->id],
            $this->basket(),
            $this->cashier(),
        );
    }

    public function test_an_approval_cannot_be_used_on_a_second_bill(): void
    {
        $this->enableWhatsApp();
        LoyaltySetting::current()->update(['is_enabled' => true, 'min_redeem_points' => 100, 'point_value' => 0.01]);
        $customer = $this->member();

        $otp = LoyaltyRedemptionOtp::factory()->verified()->forPoints(100)->create(['customer_id' => $customer->id]);
        $billing = app(BillingService::class);
        $cashier = $this->cashier();

        $billing->createSale(
            ['payment_method' => 'card', 'customer_id' => $customer->id, 'redeem_points' => 100, 'redemption_otp_id' => $otp->id],
            $this->basket(),
            $cashier,
        );

        $this->expectException(RuntimeException::class);

        $billing->createSale(
            ['payment_method' => 'card', 'customer_id' => $customer->id, 'redeem_points' => 100, 'redemption_otp_id' => $otp->id],
            $this->basket(),
            $cashier,
        );
    }

    public function test_another_members_approval_does_not_work(): void
    {
        $this->enableWhatsApp();
        LoyaltySetting::current()->update(['is_enabled' => true, 'min_redeem_points' => 100, 'point_value' => 0.01]);
        $customer = $this->member();
        $someoneElse = $this->member();

        $otp = LoyaltyRedemptionOtp::factory()->verified()->forPoints(100)->create(['customer_id' => $someoneElse->id]);

        $this->expectException(RuntimeException::class);

        app(BillingService::class)->createSale(
            ['payment_method' => 'card', 'customer_id' => $customer->id, 'redeem_points' => 100, 'redemption_otp_id' => $otp->id],
            $this->basket(),
            $this->cashier(),
        );
    }

    public function test_the_till_sends_a_code_and_records_the_message(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

        // Pinned, because the assertion below checks the exact number sent.
        $customer = $this->member(phone: '0512345678');

        $this->actingAs($this->cashier())
            ->postJson(route('billing.otp.send'), ['customer_id' => $customer->id, 'points' => 100])
            ->assertOk()
            ->assertJson(['sent' => true]);

        $this->assertDatabaseHas('whatsapp_messages', ['purpose' => 'otp', 'status' => 'sent']);
        // The number was made international from the shop's dialling code.
        Http::assertSent(fn ($request): bool => $request['to'] === '966512345678');
    }

    public function test_the_right_code_verifies_and_a_wrong_one_burns_an_attempt(): void
    {
        $this->enableWhatsApp();
        $customer = $this->member();
        $otp = LoyaltyRedemptionOtp::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($this->cashier())
            ->postJson(route('billing.otp.verify'), ['otp_id' => $otp->id, 'code' => '000000'])
            ->assertStatus(422);

        $this->assertSame(1, $otp->fresh()->attempts);
        $this->assertNull($otp->fresh()->verified_at);

        $this->actingAs($this->cashier())
            ->postJson(route('billing.otp.verify'), ['otp_id' => $otp->id, 'code' => LoyaltyRedemptionOtpFactory::CODE])
            ->assertOk()
            ->assertJson(['verified' => true]);

        $this->assertNotNull($otp->fresh()->verified_at);
    }

    public function test_guessing_runs_out_after_the_attempt_limit(): void
    {
        $this->enableWhatsApp();
        $customer = $this->member();
        $otp = LoyaltyRedemptionOtp::factory()->create(['customer_id' => $customer->id]);

        foreach (range(1, LoyaltyRedemptionOtp::MAX_ATTEMPTS) as $ignored) {
            $this->actingAs($this->cashier())
                ->postJson(route('billing.otp.verify'), ['otp_id' => $otp->id, 'code' => '000000'])
                ->assertStatus(422);
        }

        // Even the right code is refused once the attempts are gone.
        $this->actingAs($this->cashier())
            ->postJson(route('billing.otp.verify'), ['otp_id' => $otp->id, 'code' => LoyaltyRedemptionOtpFactory::CODE])
            ->assertStatus(422);

        $this->assertNull($otp->fresh()->verified_at);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $this->enableWhatsApp();
        $customer = $this->member();
        $otp = LoyaltyRedemptionOtp::factory()->expired()->create(['customer_id' => $customer->id]);

        $this->actingAs($this->cashier())
            ->postJson(route('billing.otp.verify'), ['otp_id' => $otp->id, 'code' => LoyaltyRedemptionOtpFactory::CODE])
            ->assertStatus(422);
    }

    public function test_only_the_owner_can_approve_without_a_code(): void
    {
        $this->enableWhatsApp();
        $customer = $this->member();

        $this->actingAs($this->cashier())
            ->postJson(route('billing.otp.override'), [
                'customer_id' => $customer->id, 'points' => 100, 'reason' => 'Member has no WhatsApp',
            ])
            ->assertForbidden();
    }

    public function test_an_owner_override_is_recorded_with_who_and_why(): void
    {
        $this->enableWhatsApp();
        $customer = $this->member();
        $owner = $this->owner();

        $response = $this->actingAs($owner)
            ->postJson(route('billing.otp.override'), [
                'customer_id' => $customer->id, 'points' => 100, 'reason' => 'Member has no WhatsApp',
            ])
            ->assertOk()
            ->assertJson(['verified' => true]);

        $otp = LoyaltyRedemptionOtp::findOrFail($response->json('otp_id'));

        $this->assertTrue($otp->wasOverridden());
        $this->assertSame($owner->id, $otp->overridden_by);
        $this->assertSame('Member has no WhatsApp', $otp->override_reason);

        // And it actually lets the redemption through.
        LoyaltySetting::current()->update(['is_enabled' => true, 'min_redeem_points' => 100, 'point_value' => 0.01]);

        $sale = app(BillingService::class)->createSale(
            ['payment_method' => 'card', 'customer_id' => $customer->id, 'redeem_points' => 100, 'redemption_otp_id' => $otp->id],
            $this->basket(),
            $owner,
        );

        $this->assertSame(100, $sale->loyalty_points_redeemed);
        $this->assertInstanceOf(Sale::class, $sale);
    }

    public function test_issuing_a_new_code_kills_the_one_before_it(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

        $customer = $this->member();
        $first = LoyaltyRedemptionOtp::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($this->cashier())
            ->postJson(route('billing.otp.send'), ['customer_id' => $customer->id, 'points' => 200])
            ->assertOk();

        // The older message cannot be dug out and used later.
        $this->assertNotNull($first->fresh()->consumed_at);
    }
}
