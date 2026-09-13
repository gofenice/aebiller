<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Store;
use App\Services\RazorpayGateway;
use App\Support\DisplayCurrency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Plans are priced in the base currency and, by hand, in each of the others.
 * The public site quotes whichever one the visitor is shown, and the shop is
 * then billed in that same currency.
 */
class PricingCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function central(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_domain').$path;
    }

    protected function app(string $path = '/'): string
    {
        return 'http://'.config('tenancy.app_subdomain').'.'.config('tenancy.central_domain').$path;
    }

    protected function planPricedAt(float $base, array $others = []): Plan
    {
        $plan = Plan::factory()->create([
            'name' => 'Standard',
            'monthly_price' => $base,
            'billing_period' => BillingPeriod::Monthly,
        ]);

        foreach ($others as $code => $amount) {
            PlanPrice::factory()->for($plan)->in($code, $amount)->create();
        }

        return $plan;
    }

    public function test_the_public_site_quotes_the_base_currency_by_default(): void
    {
        $this->planPricedAt(39, ['SAR' => 145]);

        $this->get($this->central())
            ->assertOk()
            ->assertSee('39')
            ->assertDontSee('145');
    }

    public function test_a_visitor_can_switch_the_currency(): void
    {
        $this->planPricedAt(39, ['SAR' => 145]);

        $this->get($this->central('/?currency=SAR'))
            ->assertOk()
            ->assertSee('145')
            ->assertSee('SAR');
    }

    public function test_the_chosen_currency_is_remembered(): void
    {
        $this->planPricedAt(39, ['SAR' => 145]);

        $this->get($this->central('/?currency=SAR'))
            ->assertCookie(DisplayCurrency::COOKIE, 'SAR');

        $this->withCookie(DisplayCurrency::COOKIE, 'SAR')
            ->get($this->central())
            ->assertSee('145');
    }

    public function test_the_choice_carries_over_to_the_sign_up_form(): void
    {
        $this->planPricedAt(39, ['SAR' => 145]);

        $this->withCookie(DisplayCurrency::COOKIE, 'SAR')
            ->get($this->app('/register'))
            ->assertOk()
            ->assertSee('145');
    }

    public function test_cloudflare_country_pre_selects_a_currency(): void
    {
        $this->planPricedAt(39, ['INR' => 2999]);

        $this->withHeader('CF-IPCountry', 'IN')
            ->get($this->central())
            ->assertSee('2,999');
    }

    public function test_the_browser_language_is_used_when_there_is_no_country_header(): void
    {
        $this->planPricedAt(39, ['GBP' => 31]);

        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->get($this->central())
            ->assertSee('31');
    }

    public function test_a_currency_that_is_not_offered_falls_back_to_the_base_one(): void
    {
        $this->planPricedAt(39, ['SAR' => 145]);

        $this->get($this->central('/?currency=ZZZ'))
            ->assertOk()
            ->assertSee('39');
    }

    public function test_a_plan_with_no_price_in_that_currency_is_quoted_in_the_base_one(): void
    {
        $plan = $this->planPricedAt(39);

        $price = $plan->amountIn('SAR');

        $this->assertSame(39.0, $price['amount']);
        $this->assertSame(config('tenancy.base_currency'), $price['currency']);
        $this->assertTrue($price['is_base']);
    }

    public function test_a_shop_is_billed_in_the_currency_it_was_quoted(): void
    {
        $plan = $this->planPricedAt(39, ['SAR' => 145]);

        $store = Store::factory()->create([
            'plan_id' => $plan->id,
            'billing_currency' => 'SAR',
            'monthly_fee' => null,
        ]);

        $this->assertSame(145.0, $store->periodFee());
        $this->assertSame('SAR', $store->billedCurrency());
        $this->assertStringContainsString('SAR 145.00', $store->feeLabel());
    }

    public function test_a_shop_on_a_currency_the_plan_has_no_price_for_is_billed_the_base_price(): void
    {
        $plan = $this->planPricedAt(39);

        $store = Store::factory()->create([
            'plan_id' => $plan->id,
            'billing_currency' => 'SAR',
            'monthly_fee' => null,
        ]);

        // The fee and the currency on the invoice have to agree: charging 39
        // and labelling it SAR would be quietly wrong.
        $this->assertSame(39.0, $store->periodFee());
        $this->assertSame(config('tenancy.base_currency'), $store->billedCurrency());
    }

    protected function enableRazorpay(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'test-api-secret',
        ]);
    }

    public function test_a_shop_paying_automatically_is_put_on_a_plan_in_its_own_currency(): void
    {
        $this->enableRazorpay();
        $plan = $this->planPricedAt(39, ['SAR' => 145]);

        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'plan_SAR1'])]);

        $this->assertSame('plan_SAR1', app(RazorpayGateway::class)->remotePlanFor($plan, 'SAR'));

        // Razorpay fixes the amount on its own plan: the riyal price, in the
        // smallest unit — not the dollar one.
        Http::assertSent(fn ($request): bool => $request['item']['amount'] === 14500
            && $request['item']['currency'] === 'SAR');

        // Remembered against the price, leaving the plan's own id for the base.
        $this->assertSame('plan_SAR1', $plan->prices()->where('currency_code', 'SAR')->value('razorpay_plan_id'));
        $this->assertNull($plan->fresh()->razorpay_plan_id);
    }

    public function test_the_base_currency_plan_is_remembered_on_the_plan_itself(): void
    {
        $this->enableRazorpay();
        $plan = $this->planPricedAt(39, ['SAR' => 145]);

        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'plan_USD1'])]);

        $this->assertSame('plan_USD1', app(RazorpayGateway::class)->remotePlanFor($plan));

        Http::assertSent(fn ($request): bool => $request['item']['amount'] === 3900
            && $request['item']['currency'] === config('tenancy.base_currency'));

        $this->assertSame('plan_USD1', $plan->fresh()->razorpay_plan_id);
    }

    public function test_a_razorpay_plan_is_made_once_per_currency_and_then_reused(): void
    {
        $this->enableRazorpay();
        $plan = $this->planPricedAt(39, ['SAR' => 145]);

        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'plan_SAR1'])]);

        $gateway = app(RazorpayGateway::class);
        $gateway->remotePlanFor($plan, 'SAR');
        $gateway->remotePlanFor($plan->fresh(), 'SAR');

        Http::assertSentCount(1);
    }

    public function test_signing_up_records_the_currency_that_was_quoted(): void
    {
        $plan = $this->planPricedAt(39, ['SAR' => 145]);

        $this->withCookie(DisplayCurrency::COOKIE, 'SAR')
            ->post($this->app('/register'), [
                'name' => 'Al Noor Super Market',
                'slug' => 'al-noor',
                'currency_code' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'owner_name' => 'Aisha Rahman',
                'owner_email' => 'aisha@example.com',
                'password' => 'password1234',
                'password_confirmation' => 'password1234',
                'plan' => $plan->slug,
                'terms' => '1',
            ])->assertRedirect();

        $store = Store::where('slug', 'al-noor')->firstOrFail();

        $this->assertSame('SAR', $store->billing_currency);
        $this->assertSame(145.0, $store->periodFee());
    }
}
