<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\ExpenseCategory;
use App\Models\LoyaltyTier;
use App\Models\PlatformUser;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The platform dashboard on the central domain: where stores are created,
 * suspended and reopened.
 */
class PlatformDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function platformUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain').$path;
    }

    protected function admin(): PlatformUser
    {
        return PlatformUser::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function storePayload(array $overrides = []): array
    {
        return [
            'name' => 'Al Noor Market',
            'slug' => 'al-noor',
            'owner_name' => 'Khalid Al Noor',
            'owner_email' => 'khalid@example.com',
            'owner_phone' => '0551112223',
            'currency_code' => 'AED',
            'currency_symbol' => 'AED ',
            'timezone' => 'Asia/Dubai',
            'expiry_alert_days' => 30,
            'admin_name' => 'Khalid Al Noor',
            'admin_email' => 'khalid@alnoor.test',
            ...$overrides,
        ];
    }

    public function test_the_platform_needs_its_own_sign_in(): void
    {
        $this->get($this->platformUrl('/'))->assertRedirect($this->platformUrl('/login'));

        $this->get($this->platformUrl('/login'))->assertOk()->assertSee('Platform sign-in');
    }

    public function test_shop_staff_cannot_reach_the_platform(): void
    {
        // Signed in as a shop's own super admin, on the shop's guard.
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get($this->platformUrl('/'))
            ->assertRedirect($this->platformUrl('/login'));
    }

    public function test_a_platform_admin_signs_in_and_sees_the_dashboard(): void
    {
        PlatformUser::factory()->create(['email' => 'admin@pgbiller.test', 'password' => Hash::make('secret-password')]);

        $this->post($this->platformUrl('/login'), ['email' => 'admin@pgbiller.test', 'password' => 'secret-password'])
            ->assertRedirect($this->platformUrl(''));

        $this->assertAuthenticatedAs(PlatformUser::first(), 'platform');

        $this->get($this->platformUrl('/'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee($this->store->name);
    }

    public function test_creating_a_store_sets_it_up_ready_to_use(): void
    {
        $response = $this->actingAs($this->admin(), 'platform')
            ->post($this->platformUrl('/stores'), $this->storePayload());

        $store = Store::where('slug', 'al-noor')->firstOrFail();

        $response->assertRedirect($this->platformUrl('/stores/al-noor'));
        $this->assertSame('AED', $store->currency_code);
        $this->assertSame(StoreStatus::Active, $store->status);

        // Its owner can sign in, and the shop starts with usable master data.
        StoreContext::runFor($store, function (): void {
            $owner = User::where('email', 'khalid@alnoor.test')->firstOrFail();

            $this->assertSame(UserRole::SuperAdmin, $owner->role);
            $this->assertGreaterThan(0, Unit::count());
            $this->assertGreaterThan(0, ExpenseCategory::count());
            $this->assertSame(4, LoyaltyTier::count());
        });

        // The generated password is shown once, on the store's page.
        $this->actingAs($this->admin(), 'platform')
            ->get($this->platformUrl('/stores/al-noor'))
            ->assertOk()
            ->assertSee('al-noor.'.config('tenancy.central_domain'));
    }

    public function test_the_new_store_is_reachable_at_its_own_address(): void
    {
        $this->actingAs($this->admin(), 'platform')
            ->post($this->platformUrl('/stores'), $this->storePayload(['admin_password' => 'owner-password']));

        $this->post('http://al-noor.'.config('tenancy.central_domain').'/login', [
            'email' => 'khalid@alnoor.test',
            'password' => 'owner-password',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_reserved_and_duplicate_addresses_are_refused(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'platform')
            ->post($this->platformUrl('/stores'), $this->storePayload(['slug' => 'www']))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin, 'platform')
            ->post($this->platformUrl('/stores'), $this->storePayload(['slug' => $this->store->slug]))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Store::count());
    }

    public function test_a_store_can_be_suspended_and_reopened(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'platform')
            ->post($this->platformUrl('/stores/'.$this->store->slug.'/suspend'), ['suspension_reason' => 'March unpaid'])
            ->assertSessionHasNoErrors();

        $this->assertSame(StoreStatus::Suspended, $this->store->fresh()->status);

        // The shop itself is closed while suspended: everything goes to the
        // payment screen, which explains why.
        $host = 'http://'.$this->store->host();
        $owner = User::factory()->superAdmin()->create();

        // The guard is named: acting as a platform admin above made 'platform'
        // the default, and the shop owner belongs to the web guard.
        $this->actingAs($owner, 'web')->get($host.'/dashboard')->assertRedirect($host.'/subscription');
        $this->actingAs($owner, 'web')->get($host.'/subscription')->assertOk()->assertSee('March unpaid');

        $this->actingAs($admin, 'platform')
            ->post($this->platformUrl('/stores/'.$this->store->slug.'/reactivate'))
            ->assertSessionHasNoErrors();

        $this->assertSame(StoreStatus::Active, $this->store->fresh()->status);
        $this->actingAs($owner, 'web')->get($host.'/dashboard')->assertOk();
    }

    public function test_store_settings_can_be_changed_from_the_platform(): void
    {
        $this->actingAs($this->admin(), 'platform')
            ->put($this->platformUrl('/stores/'.$this->store->slug), [
                ...$this->storePayload(),
                'name' => 'Renamed Market',
                'slug' => $this->store->slug,
                'currency_code' => 'INR',
                'currency_symbol' => '₹',
                'timezone' => 'Asia/Kolkata',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Market', $this->store->fresh()->name);
        $this->assertSame('INR', $this->store->fresh()->currency_code);
    }
}
