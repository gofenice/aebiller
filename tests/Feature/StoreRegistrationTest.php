<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\ExpenseCategory;
use App\Models\LoyaltyTier;
use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingCycle;
use App\Support\DisplayCurrency;
use App\Support\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Signing a shop up from the public site: it exists, it works, and it is free
 * for three days.
 */
class StoreRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    protected function app(string $path = '/'): string
    {
        return 'http://'.config('tenancy.app_subdomain').'.'.config('tenancy.central_domain').$path;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function form(array $overrides = []): array
    {
        return [
            'name' => 'Al Noor Super Market',
            'slug' => 'al-noor',
            'owner_name' => 'Khalid Al Noor',
            'owner_email' => 'khalid@alnoor.test',
            'owner_phone' => '0551112223',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
            // No currency_code: it comes from the switcher, not the form.
            'timezone' => 'Asia/Riyadh',
            'terms' => '1',
            ...$overrides,
        ];
    }

    public function test_a_shop_signs_itself_up_and_is_ready_to_use(): void
    {
        $plan = Plan::factory()->create(['slug' => 'standard', 'monthly_price' => 199, 'billing_period' => BillingPeriod::Monthly]);

        $response = $this->post($this->app('/register'), $this->form(['plan' => 'standard']));

        $store = Store::where('slug', 'al-noor')->firstOrFail();

        $response->assertRedirect($this->app('/welcome/al-noor'));
        $this->assertSame(StoreStatus::Active, $store->status);
        $this->assertSame($plan->id, $store->plan_id);
        // Nothing was posted for it: the shop trades in the base currency.
        $this->assertSame(config('tenancy.base_currency'), $store->currency_code);

        // Three days free, and the first invoice falls due the day it ends.
        $this->assertTrue($store->trial_ends_on->isSameDay(today()->addDays(3)));
        $this->assertTrue($store->isOnTrial());
        $this->assertSame(3, $store->trialDaysLeft());
        $this->assertTrue($store->next_invoice_on->isSameDay($store->trial_ends_on));
        $this->assertDatabaseCount('store_invoices', 0);

        // The owner can work immediately: account, units, categories, tiers.
        StoreContext::runFor($store, function (): void {
            $owner = User::where('email', 'khalid@alnoor.test')->firstOrFail();

            $this->assertSame(UserRole::SuperAdmin, $owner->role);
            $this->assertGreaterThan(0, Unit::count());
            $this->assertGreaterThan(0, ExpenseCategory::count());
            $this->assertSame(4, LoyaltyTier::count());
        });
    }

    public function test_the_currency_chosen_on_the_public_site_is_the_one_the_shop_gets(): void
    {
        // Picked with the switcher before signing up: it settles what the shop
        // trades in, what its receipts show, and what it is billed in.
        $this->withCookie(DisplayCurrency::COOKIE, 'SAR')
            ->post($this->app('/register'), $this->form());

        $store = Store::where('slug', 'al-noor')->firstOrFail();

        $this->assertSame('SAR', $store->currency_code);
        $this->assertSame('SAR', $store->billing_currency);
        $this->assertSame('SAR ', $store->currency_symbol);
    }

    public function test_the_owner_can_sign_in_at_their_new_address(): void
    {
        $this->post($this->app('/register'), $this->form());

        $store = Store::where('slug', 'al-noor')->firstOrFail();

        $this->post('http://'.$store->host().'/login', [
            'email' => 'khalid@alnoor.test',
            'password' => 'a-good-password',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_the_welcome_page_offers_a_one_time_link_into_the_new_shop(): void
    {
        $this->post($this->app('/register'), $this->form());
        $store = Store::where('slug', 'al-noor')->firstOrFail();

        $response = $this->get($this->app('/welcome/al-noor'))->assertOk()->assertSee($store->host());

        // Follow the signed link: it signs the owner in without a password.
        preg_match('#(http://al-noor\.[^"]+/welcome/\d+\?[^"]+)#', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'the page offers a signed sign-in link');

        $this->get(html_entity_decode($matches[1]))->assertRedirect('http://'.$store->host().'/dashboard');
        $this->assertAuthenticated();
    }

    public function test_an_unsigned_welcome_link_is_refused(): void
    {
        $this->post($this->app('/register'), $this->form());
        $store = Store::where('slug', 'al-noor')->firstOrFail();
        $owner = StoreContext::runFor($store, fn (): User => User::firstOrFail());

        $this->get('http://'.$store->host().'/welcome/'.$owner->id)->assertForbidden();
        $this->assertGuest();
    }

    public function test_a_taken_or_reserved_address_is_refused(): void
    {
        $this->post($this->app('/register'), $this->form(['slug' => $this->store->slug]))
            ->assertSessionHasErrors('slug');

        $this->post($this->app('/register'), $this->form(['slug' => 'admin']))
            ->assertSessionHasErrors('slug');

        $this->post($this->app('/register'), $this->form(['slug' => 'www']))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Store::count());
    }

    public function test_the_form_checks_an_address_as_it_is_typed(): void
    {
        $this->getJson($this->app('/register/availability?slug=brand-new'))
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('host', 'brand-new.'.config('tenancy.central_domain'));

        $this->getJson($this->app('/register/availability?slug='.$this->store->slug))
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'Already taken');

        $this->getJson($this->app('/register/availability?slug=admin'))
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'Reserved');
    }

    public function test_the_terms_have_to_be_accepted(): void
    {
        $this->post($this->app('/register'), $this->form(['terms' => null]))
            ->assertSessionHasErrors('terms');

        $this->assertSame(1, Store::count());
    }

    public function test_the_first_invoice_arrives_when_the_trial_ends(): void
    {
        Plan::factory()->create(['slug' => 'standard', 'monthly_price' => 300, 'billing_period' => BillingPeriod::Monthly]);

        $this->post($this->app('/register'), $this->form(['plan' => 'standard']));
        $store = Store::where('slug', 'al-noor')->firstOrFail();

        // Nothing during the trial.
        $this->assertSame(0, app(BillingCycle::class)->generateDueInvoices(today())['invoices']);

        // Told the day before it ends, due on the day itself, part month only.
        $this->assertSame(1, app(BillingCycle::class)->generateDueInvoices(today()->addDays(2))['invoices']);

        $invoice = StoreInvoice::firstOrFail();

        $this->assertTrue($invoice->due_on->isSameDay($store->trial_ends_on));
        $this->assertTrue($invoice->is_prorated);
        $this->assertLessThan(300, (float) $invoice->amount);
    }

    public function test_someone_who_forgets_their_address_is_pointed_back_to_it(): void
    {
        $this->post($this->app('/register'), $this->form());
        $store = Store::where('slug', 'al-noor')->firstOrFail();

        // By address…
        $this->post($this->app('/sign-in'), ['search' => 'al-noor'])
            ->assertRedirect($store->url().'/login');

        // …or by the email they signed up with.
        $this->post($this->app('/sign-in'), ['search' => 'khalid@alnoor.test'])
            ->assertOk()
            ->assertSee('Al Noor Super Market')
            ->assertSee($store->host());
    }
}
