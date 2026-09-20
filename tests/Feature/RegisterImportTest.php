<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loading a shop's handwritten register into a store: a product per line,
 * counted pieces as opening stock, and a batch per expiry date.
 */
class RegisterImportTest extends TestCase
{
    use RefreshDatabase;

    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create();

        $this->path = tempnam(sys_get_temp_dir(), 'register').'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function writeRegister(array $rows): void
    {
        file_put_contents($this->path, json_encode($rows));
    }

    public function test_it_creates_a_product_with_its_pack_size_in_the_name(): void
    {
        $this->writeRegister([
            ['serial' => '390', 'name' => 'Almarai Cream Cheese', 'size' => '500 g', 'batches' => []],
        ]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug])
            ->assertSuccessful();

        $product = Product::sole();

        $this->assertSame('Almarai Cream Cheese 500 g', $product->name);
        $this->assertSame('FSM-00001', $product->sku);
        $this->assertSame('Register no. 390', $product->description);
        $this->assertSame(0.0, (float) $product->current_stock);
        $this->assertFalse($product->track_expiry);
    }

    public function test_counted_pieces_become_opening_stock_with_a_batch_per_expiry(): void
    {
        $this->writeRegister([
            ['serial' => '391', 'name' => 'Nestle Condensed Milk', 'size' => '370 g', 'batches' => [
                ['expiry' => '2026-12-05', 'qty' => 48],
                ['expiry' => '2026-12-08', 'qty' => 3],
            ]],
        ]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug]);

        $product = Product::sole();

        $this->assertSame(51.0, (float) $product->current_stock);
        $this->assertTrue($product->track_expiry);
        $this->assertSame(2, $product->batches()->count());
        $this->assertEqualsCanonicalizing(
            ['2026-12-05', '2026-12-08'],
            $product->batches()->pluck('expires_on')->map->toDateString()->all(),
        );
        $this->assertSame(2, $product->movements()->count());
    }

    public function test_a_month_only_expiry_runs_to_the_end_of_that_month(): void
    {
        $this->writeRegister([
            ['serial' => '393', 'name' => 'Al Taie Chick Peas', 'size' => '540 g', 'batches' => [
                ['expiry' => '2027-11', 'qty' => 12],
            ]],
        ]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug]);

        $this->assertSame('2027-11-30', Product::sole()->batches()->sole()->expires_on->toDateString());
    }

    public function test_loose_produce_is_weighable_and_sold_by_the_kilo(): void
    {
        $this->writeRegister([
            ['serial' => 'v1', 'name' => 'Savala (Onion)', 'size' => null, 'weighable' => true, 'batches' => []],
        ]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug]);

        $product = Product::sole();

        $this->assertTrue($product->is_weighable);
        $this->assertSame('kg', $product->unit->code);
        $this->assertSame('Fresh Vegetables', $product->category->name);
    }

    public function test_products_are_filed_into_aisles_by_name(): void
    {
        $this->writeRegister([
            ['name' => 'Lays Salt & Vinegar', 'size' => '155 g', 'batches' => []],
            ['name' => 'Ariel Powder', 'size' => '1.5 kg', 'batches' => []],
            ['name' => 'Sputnik Widget', 'size' => null, 'batches' => []],
        ]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug]);

        $this->assertSame('Chips & Savoury', Product::where('name', 'like', 'Lays%')->sole()->category->name);
        $this->assertSame('Detergents', Product::where('name', 'like', 'Ariel%')->sole()->category->name);
        $this->assertSame('General', Product::where('name', 'like', 'Sputnik%')->sole()->category->name);
    }

    public function test_running_it_twice_does_not_duplicate_the_catalogue(): void
    {
        $this->writeRegister([
            ['name' => 'Luna Green Peas', 'size' => null, 'batches' => [['expiry' => '2027-11-22', 'qty' => 19]]],
        ]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug]);
        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug]);

        $this->assertSame(1, Product::count());
        $this->assertSame(19.0, (float) Product::sole()->current_stock);
    }

    public function test_the_same_name_in_two_pack_sizes_is_two_products(): void
    {
        $this->writeRegister([
            ['name' => 'Almarai Milk Powder', 'size' => '400 g', 'batches' => []],
            ['name' => 'Almarai Milk Powder', 'size' => '900 g', 'batches' => []],
        ]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug]);

        $this->assertSame(2, Product::count());
    }

    public function test_a_product_written_up_twice_keeps_both_counts(): void
    {
        $this->writeRegister([
            ['serial' => '53', 'name' => 'Luna Evaporated Milk', 'size' => '170 g', 'batches' => [['expiry' => '2027-02-26', 'qty' => 47]]],
            ['serial' => '56', 'name' => 'Luna Evaporated Milk', 'size' => '170 g', 'batches' => [['expiry' => '2026-10-27', 'qty' => 13]]],
        ]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug]);

        $product = Product::sole();

        $this->assertSame(60.0, (float) $product->current_stock);
        $this->assertSame(2, $product->batches()->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->writeRegister([
            ['name' => 'Kraft Cheddar Cheese', 'size' => null, 'batches' => [['expiry' => '2026-12-18', 'qty' => 14]]],
        ]);

        $this->artisan('register:import', [
            'file' => $this->path,
            '--store' => $this->store->slug,
            '--dry-run' => true,
        ])->expectsOutputToContain('Dry run');

        $this->assertSame(0, Product::count());
    }

    public function test_it_refuses_a_store_that_does_not_exist(): void
    {
        $this->writeRegister([['name' => 'Anything', 'size' => null, 'batches' => []]]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => 'nowhere'])
            ->assertFailed();
    }

    public function test_one_store_s_import_stays_out_of_another_store(): void
    {
        $this->writeRegister([
            ['name' => 'Luna Plain Cream', 'size' => '155 g', 'batches' => [['expiry' => null, 'qty' => 18]]],
        ]);

        $this->artisan('register:import', ['file' => $this->path, '--store' => $this->store->slug]);

        $this->useStore(Store::factory()->create(['slug' => 'other']));

        $this->assertSame(0, Product::count());
    }
}
