<?php

namespace Tests\Feature;

use App\Models\PlatformUser;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Closing a shop down: archived first so a mistake can be undone, and only
 * then deleted for good.
 */
class StoreArchivingTest extends TestCase
{
    use RefreshDatabase;

    protected function url(string $path = ''): string
    {
        return 'http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain').$path;
    }

    protected function admin(): PlatformUser
    {
        return PlatformUser::factory()->create();
    }

    /**
     * A store with a little of everything in it.
     */
    protected function shop(string $slug = 'al-noor'): Store
    {
        $store = Store::factory()->create(['slug' => $slug, 'name' => 'Al Noor Market']);

        StoreContext::runFor($store, function (): void {
            User::factory()->create();
            Product::factory()->create();
        });

        return $store;
    }

    public function test_archiving_hides_the_store_and_frees_its_address(): void
    {
        $store = $this->shop();

        $this->actingAs($this->admin(), 'platform')
            ->delete($this->url('/stores/'.$store->slug))
            ->assertRedirect()
            ->assertSessionHas('status');

        // The test harness serves its own store, so this one is counted apart.
        $this->assertNull(Store::query()->whereKey($store->id)->first());
        $this->assertSame(1, Store::onlyTrashed()->count());

        $archived = Store::onlyTrashed()->sole();
        $this->assertSame('al-noor', $archived->original_slug);
        $this->assertNotSame('al-noor', $archived->slug);

        // The address is free, so another shop can take it.
        $replacement = Store::factory()->create(['slug' => 'al-noor']);
        $this->assertSame('al-noor', $replacement->slug);
    }

    public function test_an_archived_store_keeps_its_data(): void
    {
        $store = $this->shop();

        $store->archive();

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_archived_store_no_longer_answers_on_its_subdomain(): void
    {
        $store = $this->shop();
        $store->archive();

        $this->get('http://al-noor.'.config('tenancy.central_domain').'/login')
            ->assertNotFound();
    }

    public function test_an_archived_store_can_be_put_back(): void
    {
        $store = $this->shop();
        $store->archive();

        $this->actingAs($this->admin(), 'platform')
            ->post($this->url('/stores/archived/'.$store->id.'/restore'))
            ->assertSessionHas('status');

        $restored = Store::whereKey($store->id)->sole();

        $this->assertFalse($restored->isArchived());
        $this->assertSame('al-noor', $restored->slug);
        $this->assertNull($restored->original_slug);
    }

    public function test_a_restored_store_keeps_a_spare_address_when_its_own_was_taken(): void
    {
        $store = $this->shop();
        $store->archive();

        Store::factory()->create(['slug' => 'al-noor', 'name' => 'Someone Else']);

        $this->actingAs($this->admin(), 'platform')
            ->post($this->url('/stores/archived/'.$store->id.'/restore'))
            ->assertSessionHas('error');

        $restored = Store::whereKey($store->id)->sole();

        $this->assertFalse($restored->isArchived());
        $this->assertNotSame('al-noor', $restored->slug);
    }

    public function test_deleting_for_good_needs_the_address_typed_out(): void
    {
        $store = $this->shop();
        $store->archive();

        $this->actingAs($this->admin(), 'platform')
            ->delete($this->url('/stores/archived/'.$store->id), ['confirm' => 'wrong-address'])
            ->assertSessionHas('error');

        $this->assertSame(1, Store::onlyTrashed()->count());
        $this->assertDatabaseCount('products', 1);
    }

    public function test_deleting_for_good_takes_the_store_s_data_with_it(): void
    {
        $store = $this->shop();
        $store->archive();

        $this->actingAs($this->admin(), 'platform')
            ->delete($this->url('/stores/archived/'.$store->id), ['confirm' => 'al-noor'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNull(Store::withTrashed()->whereKey($store->id)->first());
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_archived_list_shows_them_and_the_normal_list_does_not(): void
    {
        $store = $this->shop();
        $store->archive();

        $this->actingAs($this->admin(), 'platform')
            ->get($this->url('/stores'))
            ->assertOk()
            ->assertDontSee('Al Noor Market');

        $this->actingAs($this->admin(), 'platform')
            ->get($this->url('/stores?status=archived'))
            ->assertOk()
            ->assertSee('Al Noor Market')
            ->assertSee('Archived');
    }

    public function test_the_sweep_leaves_a_recent_archive_alone(): void
    {
        $store = $this->shop();
        $store->archive();

        $this->artisan('stores:purge-archived')->assertSuccessful();

        $this->assertSame(1, Store::onlyTrashed()->count());
    }

    public function test_the_sweep_deletes_one_past_the_keeping_period(): void
    {
        $store = $this->shop();
        $store->archive();
        $store->forceFill(['deleted_at' => now()->subDays(Store::KEEP_ARCHIVED_DAYS + 1)])->save();

        $this->artisan('stores:purge-archived')->assertSuccessful();

        $this->assertNull(Store::withTrashed()->whereKey($store->id)->first());
        $this->assertDatabaseCount('products', 0);
    }

    public function test_shop_staff_cannot_archive_anything(): void
    {
        $store = $this->shop();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete($this->url('/stores/'.$store->slug))
            ->assertRedirect($this->url('/login'));

        $this->assertFalse($store->fresh()->isArchived());
    }
}
