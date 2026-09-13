<?php

namespace Tests;

use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    /**
     * The store every feature test runs inside, standing in for a real
     * subdomain such as fathima.pgbiller.com.
     */
    protected ?Store $store = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            return;
        }

        $this->store = Store::factory()->create(['slug' => 'teststore', 'name' => 'Test Super Market']);

        $this->useStore($this->store);
    }

    /**
     * Serve the rest of the test as this store: requests are addressed to its
     * subdomain and models read and write its data.
     */
    protected function useStore(Store $store): void
    {
        StoreContext::set($store);

        URL::defaults(['store' => $store->slug]);
        // Relative paths in tests ("/dashboard") resolve to the store's host.
        config(['app.url' => 'http://'.$store->host()]);
        URL::forceRootUrl(config('app.url'));
    }

    protected function tearDown(): void
    {
        StoreContext::forget();

        parent::tearDown();
    }
}
