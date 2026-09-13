<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Four kinds of address: the public site, the platform's admin, the customer
 * area, and each shop's own subdomain.
 */
class MarketingSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function central(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_domain').$path;
    }

    protected function admin(string $path = '/'): string
    {
        return 'http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain').$path;
    }

    protected function app(string $path = '/'): string
    {
        return 'http://'.config('tenancy.app_subdomain').'.'.config('tenancy.central_domain').$path;
    }

    public function test_the_bare_domain_shows_the_public_site(): void
    {
        Plan::factory()->create(['name' => 'Standard', 'monthly_price' => 199, 'billing_period' => BillingPeriod::Monthly]);

        $this->get($this->central())
            ->assertOk()
            ->assertSee('AE Biller')
            ->assertSee('Start your 3-day trial')
            ->assertSee('Standard')
            ->assertSee('199')
            // …and it links to the customer area to sign up.
            ->assertSee($this->app('/register'));
    }

    public function test_the_public_site_quotes_only_plans_that_are_offered(): void
    {
        Plan::factory()->create(['name' => 'Offered Plan', 'is_active' => true]);
        Plan::factory()->create(['name' => 'Retired Plan', 'is_active' => false]);

        $this->get($this->central())
            ->assertSee('Offered Plan')
            ->assertDontSee('Retired Plan');
    }

    public function test_a_plan_can_be_paid_monthly_or_yearly_from_the_same_card(): void
    {
        Plan::factory()->create([
            'name' => 'Gold',
            'monthly_price' => 100,
            'yearly_discount_percent' => 20,
            'billing_period' => BillingPeriod::Monthly,
        ]);

        // Both figures are rendered and the toggle hides one, so the price is
        // always the server's rather than worked out in the browser.
        $this->get($this->central())
            ->assertOk()
            ->assertSee('Gold')
            ->assertSee('100')
            // 100 x 12 = 1200, less 20% = 960
            ->assertSee('960');
    }

    public function test_a_backend_only_plan_is_kept_off_the_public_site(): void
    {
        Plan::factory()->create(['name' => 'Chosen Plan']);
        Plan::factory()->lifetimeFree()->create(['name' => 'Lifetime Free']);

        $this->get($this->central())
            ->assertSee('Chosen Plan')
            ->assertDontSee('Lifetime Free');
    }

    public function test_the_platform_admin_lives_on_its_own_subdomain(): void
    {
        $this->get($this->admin())->assertRedirect($this->admin('/login'));
        $this->get($this->admin('/login'))->assertOk()->assertSee('Platform sign-in');

        // …and is not reachable from the public domain.
        $this->get($this->central('/stores'))->assertNotFound();
    }

    public function test_the_platform_dashboard_still_works_on_the_admin_subdomain(): void
    {
        $this->actingAs(PlatformUser::factory()->create(), 'platform')
            ->get($this->admin())
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_the_customer_area_lives_on_its_own_subdomain(): void
    {
        $this->get($this->app())->assertRedirect($this->app('/register'));
        $this->get($this->app('/register'))->assertOk()->assertSee('Create your shop');
        $this->get($this->app('/sign-in'))->assertOk()->assertSee('Sign in to your shop');
    }

    public function test_a_shop_still_answers_on_its_own_subdomain(): void
    {
        $this->get('http://'.$this->store->host().'/login')->assertOk();
    }

    public function test_the_razorpay_webhook_stays_on_the_bare_domain(): void
    {
        // Unsigned, so it is refused — but the address is still there.
        $this->postJson($this->central('/webhooks/razorpay'), ['event' => 'payment_link.paid'])
            ->assertStatus(400);
    }
}
