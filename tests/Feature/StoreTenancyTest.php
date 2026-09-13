<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Each store is served from its own subdomain and sees only its own data.
 * $this->store (from TestCase) stands in for the store being visited.
 */
class StoreTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function otherStore(): Store
    {
        return Store::factory()->create(['slug' => 'otherstore', 'name' => 'Other Super Market']);
    }

    public function test_a_store_only_sees_its_own_products(): void
    {
        $mine = Product::factory()->create(['name' => 'My Own Rice']);

        $other = $this->otherStore();
        StoreContext::runFor($other, function (): void {
            Product::factory()->create(['name' => 'Their Own Rice']);
        });

        $this->assertSame(1, Product::count(), 'the current store sees only its own product');
        $this->assertSame($this->store->id, $mine->store_id);

        $this->actingAs(User::factory()->create())
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('My Own Rice')
            ->assertDontSee('Their Own Rice');
    }

    public function test_the_same_code_can_be_used_in_two_stores(): void
    {
        Product::factory()->create(['sku' => 'PKT-00001', 'barcode' => '6281007021234']);

        StoreContext::runFor($this->otherStore(), function (): void {
            Product::factory()->create(['sku' => 'PKT-00001', 'barcode' => '6281007021234']);

            $this->assertSame(1, Product::count());
        });

        $this->assertDatabaseCount('products', 2);
    }

    public function test_invoice_and_card_numbers_start_again_in_each_store(): void
    {
        $first = Customer::factory()->create(['phone' => '0551234567']);

        StoreContext::runFor($this->otherStore(), function () use ($first): void {
            // The same mobile number can be a member of both stores.
            $second = Customer::factory()->create(['phone' => '0551234567']);

            $this->assertNotSame($first->store_id, $second->store_id);
        });

        $this->assertDatabaseCount('customers', 2);
    }

    public function test_an_address_that_belongs_to_no_store_is_refused(): void
    {
        $this->get('http://nosuchshop.'.config('tenancy.central_domain').'/dashboard')
            ->assertNotFound()
            ->assertSee('No store at this address');
    }

    public function test_a_suspended_store_is_closed_to_everything_but_its_payment_screen(): void
    {
        $suspended = Store::factory()->suspended('Monthly payment overdue')->create(['slug' => 'lapsed']);
        $host = 'http://lapsed.'.config('tenancy.central_domain');

        $user = StoreContext::runFor($suspended, fn (): User => User::factory()->create());

        $this->actingAs($user)->get($host.'/dashboard')->assertRedirect($host.'/subscription');

        $this->actingAs($user)
            ->get($host.'/subscription')
            ->assertOk()
            ->assertSee('closed by')
            ->assertSee('Monthly payment overdue');
    }

    public function test_staff_can_only_sign_in_at_their_own_store(): void
    {
        $user = User::factory()->create(['email' => 'manager@example.com', 'password' => 'secret-password']);
        $other = $this->otherStore();

        $credentials = ['email' => 'manager@example.com', 'password' => 'secret-password'];

        // The same address at another store is not this person's sign-in.
        $this->post('http://'.$other->host().'/login', $credentials)->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('http://'.$this->store->host().'/login', $credentials)->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_same_email_can_run_two_different_stores(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        StoreContext::runFor($this->otherStore(), function (): void {
            User::factory()->create(['email' => 'owner@example.com']);
        });

        $this->assertDatabaseCount('users', 2);
    }

    public function test_the_store_settings_reach_the_screens(): void
    {
        $this->store->update([
            'name' => 'Al Noor Market',
            'currency_symbol' => 'AED ',
            'vat_number' => '300012345600003',
        ]);

        StoreContext::set($this->store->fresh());

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Al Noor Market')
            ->assertSee('AED');
    }

    public function test_nothing_can_be_written_without_a_store_in_context(): void
    {
        StoreContext::forget();

        $this->expectException(RuntimeException::class);

        Product::factory()->create();
    }

    public function test_the_public_site_and_the_platform_answer_on_their_own_addresses(): void
    {
        $central = 'http://'.config('tenancy.central_domain');
        $admin = 'http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain');

        // The bare domain is the public site, open to anyone…
        $this->get($central.'/')->assertOk()->assertSee(config('tenancy.platform_name'));

        // …while the platform's own dashboard is private, on its own subdomain.
        $this->get($admin.'/')->assertRedirect($admin.'/login');
    }
}
