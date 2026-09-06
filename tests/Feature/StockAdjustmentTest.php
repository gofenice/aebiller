<?php

namespace Tests\Feature;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_writing_off_damage_lowers_the_balance(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['current_stock' => 20, 'cost_price' => 30]);

        $this->actingAs($user)->post(route('stock-adjustments.store'), [
            'adjustment_date' => now()->toDateString(),
            'reason' => AdjustmentReason::Damage->value,
            'notes' => 'Crushed cartons',
            'items' => [
                ['product_id' => $product->id, 'direction' => 'out', 'quantity' => 4],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(16.0, (float) $product->fresh()->current_stock);

        $adjustment = StockAdjustment::firstOrFail();
        $this->assertSame(120.0, (float) $adjustment->total_value);

        $this->assertDatabaseHas('stock_adjustment_items', [
            'stock_adjustment_id' => $adjustment->id,
            'stock_before' => 20,
            'stock_after' => 16,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => MovementType::AdjustmentOut->value,
            'balance_after' => 16,
        ]);
    }

    public function test_stock_found_back_can_be_added(): void
    {
        $product = Product::factory()->create(['current_stock' => 5]);

        $this->actingAs(User::factory()->create())->post(route('stock-adjustments.store'), [
            'adjustment_date' => now()->toDateString(),
            'reason' => AdjustmentReason::Other->value,
            'items' => [
                ['product_id' => $product->id, 'direction' => 'in', 'quantity' => 3],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(8.0, (float) $product->fresh()->current_stock);
    }

    public function test_a_physical_count_sets_the_balance_to_the_counted_figure(): void
    {
        $short = Product::factory()->create(['current_stock' => 50]);
        $over = Product::factory()->create(['current_stock' => 10]);

        $this->actingAs(User::factory()->create())->post(route('stock-adjustments.store'), [
            'adjustment_date' => now()->toDateString(),
            'reason' => AdjustmentReason::StockCount->value,
            'items' => [
                ['product_id' => $short->id, 'direction' => 'out', 'quantity' => 46],
                ['product_id' => $over->id, 'direction' => 'out', 'quantity' => 14],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(46.0, (float) $short->fresh()->current_stock);
        $this->assertSame(14.0, (float) $over->fresh()->current_stock);

        $this->assertDatabaseHas('stock_adjustment_items', [
            'product_id' => $over->id,
            'direction' => 'in',
            'quantity' => 4,
        ]);
    }

    public function test_more_stock_than_is_on_hand_cannot_be_removed(): void
    {
        $product = Product::factory()->create(['current_stock' => 2]);

        $this->actingAs(User::factory()->create())->post(route('stock-adjustments.store'), [
            'adjustment_date' => now()->toDateString(),
            'reason' => AdjustmentReason::Wastage->value,
            'items' => [
                ['product_id' => $product->id, 'direction' => 'out', 'quantity' => 5],
            ],
        ])->assertSessionHas('error');

        $this->assertSame(2.0, (float) $product->fresh()->current_stock);
        $this->assertDatabaseCount('stock_adjustments', 0);
    }

    public function test_removing_stock_empties_the_oldest_batch_first(): void
    {
        $product = Product::factory()->perishable()->create(['current_stock' => 0]);

        $oldest = $product->batches()->create([
            'batch_number' => 'OLD',
            'expires_on' => now()->addDays(3)->toDateString(),
            'received_quantity' => 5,
            'quantity' => 5,
            'cost_price' => 20,
        ]);

        $newest = $product->batches()->create([
            'batch_number' => 'NEW',
            'expires_on' => now()->addDays(30)->toDateString(),
            'received_quantity' => 10,
            'quantity' => 10,
            'cost_price' => 20,
        ]);

        $product->forceFill(['current_stock' => 15])->save();

        $this->actingAs(User::factory()->create())->post(route('stock-adjustments.store'), [
            'adjustment_date' => now()->toDateString(),
            'reason' => AdjustmentReason::Expiry->value,
            'items' => [
                ['product_id' => $product->id, 'direction' => 'out', 'quantity' => 7],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $oldest->fresh()->quantity);
        $this->assertSame(8.0, (float) $newest->fresh()->quantity);
        $this->assertSame(8.0, (float) $product->fresh()->current_stock);
    }
}
