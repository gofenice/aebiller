<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\Sale;
use App\Models\User;
use App\Services\BarcodeGenerator;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LoyaltyTier::factory()->create(['name' => 'Classic', 'min_spend' => 0, 'earn_multiplier' => 1]);
        LoyaltyTier::factory()->reachedAt(1000, 2)->create(['name' => 'Gold']);
    }

    protected function loyalty(): LoyaltyService
    {
        return app(LoyaltyService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function enrol(array $attributes = []): Customer
    {
        return $this->loyalty()->enrol([
            'name' => 'Aisha Rahman',
            'phone' => '0551234567',
            ...$attributes,
        ], User::factory()->create());
    }

    public function test_enrolling_a_member_issues_a_scannable_card_and_the_welcome_bonus(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('customers.store'), [
            'name' => 'Aisha Rahman',
            'phone' => '+966 55 123 4567',
            'marketing_opt_in' => '1',
        ]);

        $customer = Customer::with('activeCard', 'tier')->firstOrFail();

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertSame('0551234567', $customer->phone);
        $this->assertSame('Classic', $customer->tier->name);
        $this->assertStringStartsWith(LoyaltyService::CARD_PREFIX, $customer->activeCard->number);
        $this->assertTrue(app(BarcodeGenerator::class)->isValidEan13($customer->activeCard->number));
        $this->assertSame(50, $customer->points_balance);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::WelcomeBonus->value,
            'points' => 50,
        ]);
    }

    public function test_card_numbers_run_in_sequence(): void
    {
        $first = $this->enrol();
        $second = $this->enrol(['phone' => '0559999999']);

        // 29 + a ten-digit sequence + the EAN-13 check digit.
        $this->assertSame('2900000000018', $first->activeCard->number);
        $this->assertSame('2900000000025', $second->activeCard->number);
    }

    public function test_a_mobile_number_belongs_to_one_member_however_it_is_typed(): void
    {
        $this->enrol();

        $this->actingAs(User::factory()->create())
            ->post(route('customers.store'), ['name' => 'Someone Else', 'phone' => '055 123 4567'])
            ->assertSessionHasErrors('phone');

        $this->assertSame(1, Customer::count());
    }

    public function test_a_lost_card_stops_scanning_and_the_points_stay_with_the_member(): void
    {
        $customer = $this->enrol();
        $oldNumber = $customer->activeCard->number;

        $this->actingAs(User::factory()->create())
            ->post(route('customers.replace-card', $customer), ['reason' => 'lost'])
            ->assertSessionHasNoErrors();

        $customer->refresh()->load('activeCard');

        $this->assertNotSame($oldNumber, $customer->activeCard->number);
        $this->assertSame(CardStatus::Lost, $customer->cards()->where('number', $oldNumber)->first()->status);
        $this->assertSame(50, $customer->points_balance);

        $lookup = $this->loyalty()->findMember($oldNumber);
        $this->assertNull($lookup['customer']);
        $this->assertStringContainsString('reported lost', $lookup['error']);

        $this->assertTrue($this->loyalty()->findMember($customer->activeCard->number)['customer']->is($customer));
    }

    public function test_unspent_points_lapse_on_their_expiry_date(): void
    {
        $this->travelTo('2026-01-10 10:00');
        $customer = $this->enrol();

        $this->travelTo('2027-01-09 12:00');
        $this->assertSame(['members' => 0, 'points' => 0], $this->loyalty()->expirePoints());

        $this->travelTo('2027-01-11 00:10');
        $this->assertSame(['members' => 1, 'points' => 50], $this->loyalty()->expirePoints());

        $this->assertSame(0, $customer->fresh()->points_balance);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::Expiry->value,
            'points' => -50,
            'balance_after' => 0,
        ]);
    }

    public function test_spending_uses_the_soonest_expiring_points_first(): void
    {
        $owner = User::factory()->superAdmin()->create();

        $this->travelTo('2026-01-10 10:00');
        $customer = $this->enrol(); // 50 points, lapse January 2027

        $this->travelTo('2026-07-10 10:00');
        $this->loyalty()->adjust($customer, 100, 'Goodwill', $owner); // lapse July 2027
        $this->loyalty()->adjust($customer, -80, 'Correction', $owner); // uses the 50 first, then 30

        $this->travelTo('2027-01-11 00:10');
        $this->assertSame(0, $this->loyalty()->expirePoints()['points'], 'the oldest points were already spent');

        $this->travelTo('2027-07-11 00:10');
        $this->assertSame(70, $this->loyalty()->expirePoints()['points']);
        $this->assertSame(0, $customer->fresh()->points_balance);
    }

    public function test_the_birthday_bonus_is_credited_once_a_year(): void
    {
        $customer = $this->enrol(['birth_date' => now()->subYears(30)->toDateString()]);
        $this->enrol(['phone' => '0559999999', 'birth_date' => now()->subYears(30)->addDay()->toDateString()]);

        $this->assertSame(1, $this->loyalty()->awardBirthdayBonuses());
        $this->assertSame(0, $this->loyalty()->awardBirthdayBonuses(), 'a second run the same day credits nothing');

        $this->assertSame(150, $customer->fresh()->points_balance);
    }

    public function test_a_29_february_birthday_is_celebrated_on_the_28th_in_other_years(): void
    {
        $this->travelTo('2027-02-28 00:30');
        $customer = $this->enrol(['birth_date' => '1996-02-29']);

        $this->assertSame(1, $this->loyalty()->awardBirthdayBonuses());
        $this->assertSame(150, $customer->fresh()->points_balance);
    }

    public function test_only_a_super_admin_can_adjust_points(): void
    {
        $customer = $this->enrol();

        $this->actingAs(User::factory()->create())
            ->post(route('customers.adjust-points', $customer), ['points' => 500, 'reason' => 'Nice customer'])
            ->assertForbidden();

        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)
            ->post(route('customers.adjust-points', $customer), ['points' => 500, 'reason' => 'Damaged eggs refund'])
            ->assertSessionHasNoErrors();

        $this->assertSame(550, $customer->fresh()->points_balance);

        $this->actingAs($owner)
            ->post(route('customers.adjust-points', $customer), ['points' => -600, 'reason' => 'Too many'])
            ->assertSessionHas('error');

        $this->assertSame(550, $customer->fresh()->points_balance);
    }

    public function test_a_members_card_prints_with_its_barcode(): void
    {
        $customer = $this->enrol();

        $this->actingAs(User::factory()->create())
            ->get(route('customers.card', $customer))
            ->assertOk()
            ->assertSee($customer->activeCard->formattedNumber())
            ->assertSee('<svg', false)
            ->assertSee('size: A4 portrait', false);

        $this->actingAs(User::factory()->create())
            ->get(route('customers.card', ['customer' => $customer, 'layout' => 'pvc']))
            ->assertOk()
            ->assertSee('size: 85.6mm 53.98mm', false);
    }

    public function test_ticked_members_print_on_one_sheet_and_the_designs_preview_without_members(): void
    {
        $first = $this->enrol();
        $second = $this->enrol(['name' => 'Yusuf Ibrahim', 'phone' => '0559999999']);
        $this->enrol(['name' => 'Not Ticked', 'phone' => '0558888888']);

        $this->actingAs(User::factory()->create())
            ->get(route('customers.cards', ['customers' => [$first->id, $second->id]]))
            ->assertOk()
            ->assertSee('Aisha Rahman')
            ->assertSee('Yusuf Ibrahim')
            ->assertDontSee('Not Ticked');

        $this->actingAs(User::factory()->create())
            ->get(route('customers.card-designs'))
            ->assertOk()
            ->assertSee('Classic')
            ->assertSee('Gold');
    }

    public function test_the_member_page_behind_the_card_qr_is_public(): void
    {
        $customer = $this->enrol();

        $this->get(route('member.show', $customer->uuid))
            ->assertOk()
            ->assertSee('Hello Aisha')
            ->assertSee('50');
    }

    public function test_the_member_profile_shows_card_and_history(): void
    {
        $customer = $this->enrol();

        $this->actingAs(User::factory()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->activeCard->formattedNumber())
            ->assertSee('Welcome bonus');

        $this->actingAs(User::factory()->create())
            ->get(route('customers.index', ['search' => '123 4567']))
            ->assertOk()
            ->assertSee('Aisha Rahman');
    }

    public function test_only_a_super_admin_can_change_the_programme_rules(): void
    {
        $this->actingAs(User::factory()->create())->get(route('loyalty.settings'))->assertForbidden();

        $owner = User::factory()->superAdmin()->create();
        $this->actingAs($owner)->get(route('loyalty.settings'))->assertOk();

        [$classic, $gold] = LoyaltyTier::ordered()->get()->all();

        $this->actingAs($owner)->put(route('loyalty.settings.update'), [
            'is_enabled' => '1',
            'program_name' => 'Fathima Plus',
            'points_per_currency' => 2,
            'point_value' => 0.005,
            'min_redeem_points' => 200,
            'max_redeem_percent' => 30,
            'points_expiry_months' => 6,
            'welcome_bonus' => 20,
            'birthday_bonus' => 0,
            'tier_window_months' => 12,
            'tiers' => [
                ['id' => $classic->id, 'name' => 'Classic', 'min_spend' => 0, 'earn_multiplier' => 1, 'card_theme' => 'emerald'],
                ['id' => $gold->id, 'name' => 'Gold', 'min_spend' => 1000, 'earn_multiplier' => 2, 'card_theme' => 'gold', 'remove' => '1'],
                ['name' => 'Silver', 'min_spend' => 500, 'earn_multiplier' => 1.25, 'card_theme' => 'silver'],
            ],
        ])->assertSessionHasNoErrors();

        $settings = LoyaltySetting::current();
        $this->assertSame('Fathima Plus', $settings->program_name);
        $this->assertSame(200, $settings->min_redeem_points);
        $this->assertSame(['Classic', 'Silver'], LoyaltyTier::ordered()->pluck('name')->all());
    }

    public function test_the_tier_ladder_must_keep_a_starting_tier(): void
    {
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)->put(route('loyalty.settings.update'), [
            'program_name' => 'Fathima Rewards',
            'points_per_currency' => 1,
            'point_value' => 0.01,
            'min_redeem_points' => 100,
            'max_redeem_percent' => 50,
            'points_expiry_months' => 12,
            'welcome_bonus' => 50,
            'birthday_bonus' => 100,
            'tier_window_months' => 12,
            'tiers' => [
                ['name' => 'Gold', 'min_spend' => 1000, 'earn_multiplier' => 2, 'card_theme' => 'gold'],
            ],
        ])->assertSessionHasErrors('tiers');
    }

    public function test_a_member_with_bills_cannot_be_deleted(): void
    {
        $customer = $this->enrol();
        Sale::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('customers.destroy', $customer))
            ->assertSessionHas('error');

        $this->assertModelExists($customer);
    }
}
