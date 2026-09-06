<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\StockEntryType;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_goods_raises_the_stock_balance(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['current_stock' => 10, 'cost_price' => 100]);

        $this->actingAs($user)->post(route('stock-entries.store'), [
            'type' => StockEntryType::Purchase->value,
            'entry_date' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'invoice_number' => 'INV-001',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20, 'free_quantity' => 2, 'unit_cost' => 110, 'tax_percent' => 5],
            ],
        ])->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertSame(32.0, (float) $product->current_stock);
        $this->assertSame(110.0, (float) $product->cost_price, 'the latest purchase price should update the master');

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => MovementType::Purchase->value,
            'direction' => 'in',
            'quantity' => 22,
            'balance_after' => 32,
        ]);
    }

    public function test_entry_totals_are_worked_out_from_the_lines(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('stock-entries.store'), [
            'type' => StockEntryType::Purchase->value,
            'entry_date' => now()->toDateString(),
            'supplier_id' => Supplier::factory()->create()->id,
            'other_charges' => 50,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 100, 'discount_percent' => 10, 'tax_percent' => 5],
            ],
        ])->assertSessionHasNoErrors();

        $entry = StockEntry::firstOrFail();

        // 10 x 100 = 1000, less 10% = 900, plus 5% GST = 945, plus 50 charges.
        $this->assertSame(900.0, (float) $entry->subtotal);
        $this->assertSame(100.0, (float) $entry->discount_total);
        $this->assertSame(45.0, (float) $entry->tax_total);
        $this->assertSame(995.0, (float) $entry->grand_total);
    }

    public function test_a_batch_is_opened_for_products_that_track_expiry(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->perishable()->create();
        $expiry = now()->addDays(20)->toDateString();

        $this->actingAs($user)->post(route('stock-entries.store'), [
            'type' => StockEntryType::Purchase->value,
            'entry_date' => now()->toDateString(),
            'supplier_id' => Supplier::factory()->create()->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 15, 'unit_cost' => 60, 'batch_number' => 'B2201', 'expires_on' => $expiry],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $product->id,
            'batch_number' => 'B2201',
            'quantity' => 15,
        ]);
    }

    public function test_a_purchase_needs_a_supplier(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('stock-entries.store'), [
            'type' => StockEntryType::Purchase->value,
            'entry_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 10]],
        ])->assertSessionHasErrors('supplier_id');
    }

    public function test_an_entry_needs_at_least_one_line(): void
    {
        $this->actingAs(User::factory()->create())->post(route('stock-entries.store'), [
            'type' => StockEntryType::Opening->value,
            'entry_date' => now()->toDateString(),
            'items' => [['product_id' => '', 'quantity' => '']],
        ])->assertSessionHasErrors('items');
    }

    public function test_the_same_product_cannot_appear_twice(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('stock-entries.store'), [
            'type' => StockEntryType::Opening->value,
            'entry_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 10],
                ['product_id' => $product->id, 'quantity' => 3, 'unit_cost' => 10],
            ],
        ])->assertSessionHasErrors('items.0.product_id');
    }

    public function test_reference_numbers_run_in_sequence(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $products = Product::factory()->count(2)->create();

        foreach ($products as $product) {
            $this->actingAs($user)->post(route('stock-entries.store'), [
                'type' => StockEntryType::Purchase->value,
                'entry_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 10]],
            ])->assertSessionHasNoErrors();
        }

        $period = now()->format('ym');

        $this->assertSame(
            ["GRN-{$period}-0001", "GRN-{$period}-0002"],
            StockEntry::orderBy('id')->pluck('reference_no')->all(),
        );
    }

    public function test_a_super_admin_can_reverse_an_entry(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $product = Product::factory()->create(['current_stock' => 0]);

        $this->actingAs($owner)->post(route('stock-entries.store'), [
            'type' => StockEntryType::Purchase->value,
            'entry_date' => now()->toDateString(),
            'supplier_id' => Supplier::factory()->create()->id,
            'items' => [['product_id' => $product->id, 'quantity' => 12, 'unit_cost' => 40]],
        ])->assertSessionHasNoErrors();

        $entry = StockEntry::firstOrFail();
        $this->assertSame(12.0, (float) $product->fresh()->current_stock);

        $this->actingAs($owner)->delete(route('stock-entries.destroy', $entry))->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $product->fresh()->current_stock);
        $this->assertDatabaseMissing('stock_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => MovementType::EntryReversal->value,
            'direction' => 'out',
        ]);
    }

    public function test_an_entry_cannot_be_reversed_once_the_goods_have_gone(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $product = Product::factory()->create(['current_stock' => 0]);

        $this->actingAs($owner)->post(route('stock-entries.store'), [
            'type' => StockEntryType::Purchase->value,
            'entry_date' => now()->toDateString(),
            'supplier_id' => Supplier::factory()->create()->id,
            'items' => [['product_id' => $product->id, 'quantity' => 12, 'unit_cost' => 40]],
        ])->assertSessionHasNoErrors();

        $product->forceFill(['current_stock' => 3])->save();

        $this->actingAs($owner)
            ->delete(route('stock-entries.destroy', StockEntry::firstOrFail()))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('stock_entries', 1);
    }
}
