<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\PlatformUser;
use App\Models\User;
use App\Services\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Setting the platform's Razorpay account up from the admin screens rather
 * than over SSH.
 */
class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function url(string $path = '/settings'): string
    {
        return 'http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain').$path;
    }

    protected function admin(): PlatformUser
    {
        return PlatformUser::factory()->create();
    }

    public function test_the_page_needs_a_platform_sign_in(): void
    {
        $this->get($this->url())->assertRedirect($this->url('/login'));
    }

    public function test_shop_staff_cannot_reach_it(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get($this->url())
            ->assertRedirect($this->url('/login'));
    }

    public function test_the_keys_are_saved_and_the_webhook_address_is_shown(): void
    {
        $this->actingAs($this->admin(), 'platform')
            ->put($this->url(), [
                'razorpay_key_id' => 'rzp_live_ABC123',
                'razorpay_key_secret' => 'the-key-secret',
                'razorpay_webhook_secret' => 'the-webhook-secret',
            ])
            ->assertRedirect($this->url())
            ->assertSessionHas('status');

        $settings = PlatformSetting::current();

        $this->assertSame('rzp_live_ABC123', $settings->razorpay_key_id);
        $this->assertSame('the-key-secret', $settings->razorpay_key_secret);
        $this->assertSame('the-webhook-secret', $settings->razorpay_webhook_secret);

        // Secrets are encrypted at rest.
        $this->assertNotSame('the-key-secret', $settings->getRawOriginal('razorpay_key_secret'));

        $this->actingAs($this->admin(), 'platform')
            ->get($this->url())
            ->assertOk()
            ->assertSee(route('webhooks.razorpay'))
            ->assertSee('subscription.charged')
            ->assertDontSee('the-key-secret');
    }

    public function test_saved_keys_are_used_in_place_of_the_environment(): void
    {
        config(['services.razorpay.key' => 'rzp_test_FROM_ENV', 'services.razorpay.secret' => 'env-secret']);

        PlatformSetting::create([
            'razorpay_key_id' => 'rzp_live_SAVED',
            'razorpay_key_secret' => 'saved-secret',
        ]);

        PlatformSetting::applyToConfig();

        $this->assertSame('rzp_live_SAVED', config('services.razorpay.key'));
        $this->assertSame('saved-secret', config('services.razorpay.secret'));
    }

    public function test_an_empty_field_leaves_the_environment_key_alone(): void
    {
        config(['services.razorpay.key' => 'rzp_test_FROM_ENV']);

        PlatformSetting::create(['razorpay_key_id' => null, 'razorpay_key_secret' => 'saved-secret']);

        PlatformSetting::applyToConfig();

        $this->assertSame('rzp_test_FROM_ENV', config('services.razorpay.key'));
    }

    public function test_leaving_a_secret_blank_keeps_the_saved_one(): void
    {
        PlatformSetting::create([
            'razorpay_key_id' => 'rzp_live_ABC123',
            'razorpay_key_secret' => 'the-original-secret',
        ]);

        $this->actingAs($this->admin(), 'platform')
            ->put($this->url(), [
                'razorpay_key_id' => 'rzp_live_ABC123',
                'razorpay_key_secret' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('the-original-secret', PlatformSetting::current()->razorpay_key_secret);
    }

    public function test_a_key_id_of_the_wrong_shape_is_refused(): void
    {
        $this->actingAs($this->admin(), 'platform')
            ->put($this->url(), ['razorpay_key_id' => 'not-a-razorpay-key'])
            ->assertSessionHasErrors('razorpay_key_id');
    }

    public function test_a_test_key_is_shown_as_test_mode(): void
    {
        PlatformSetting::create(['razorpay_key_id' => 'rzp_test_ABC', 'razorpay_key_secret' => 's']);

        $this->actingAs($this->admin(), 'platform')
            ->get($this->url())
            ->assertOk()
            ->assertSee('Test mode');
    }

    public function test_the_connection_test_reports_that_the_keys_work(): void
    {
        config(['services.razorpay.key' => 'rzp_test_ABC', 'services.razorpay.secret' => 'secret']);
        Http::fake(['api.razorpay.com/*' => Http::response(['entity' => 'collection', 'count' => 0])]);

        $this->actingAs($this->admin(), 'platform')
            ->post($this->url('/settings/razorpay/test'))
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_the_connection_test_reports_a_refusal(): void
    {
        config(['services.razorpay.key' => 'rzp_test_ABC', 'services.razorpay.secret' => 'wrong']);
        Http::fake([
            'api.razorpay.com/*' => Http::response(['error' => ['description' => 'Authentication failed']], 401),
        ]);

        $this->actingAs($this->admin(), 'platform')
            ->post($this->url('/settings/razorpay/test'))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Authentication failed'));
    }

    public function test_the_connection_test_asks_for_keys_first(): void
    {
        config(['services.razorpay.key' => null, 'services.razorpay.secret' => null]);

        $this->actingAs($this->admin(), 'platform')
            ->post($this->url('/settings/razorpay/test'))
            ->assertSessionHas('error');
    }

    public function test_saving_clears_the_cached_settings(): void
    {
        Cache::put(PlatformSetting::CACHE_KEY, ['razorpay_key_id' => 'rzp_test_STALE']);

        PlatformSetting::create(['razorpay_key_id' => 'rzp_live_FRESH']);

        $this->assertNull(Cache::get(PlatformSetting::CACHE_KEY));
    }

    public function test_the_gateway_reads_the_keys_saved_here(): void
    {
        PlatformSetting::create([
            'razorpay_key_id' => 'rzp_live_SAVED',
            'razorpay_key_secret' => 'saved-secret',
        ]);

        PlatformSetting::applyToConfig();

        $this->assertTrue(app(RazorpayGateway::class)->enabled());
    }
}
