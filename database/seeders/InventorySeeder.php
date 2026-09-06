<?php

namespace Database\Seeders;

use App\Enums\AdjustmentReason;
use App\Enums\StockEntryType;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class InventorySeeder extends Seeder
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * Put stock on the shelf through the same path the UI uses, so the demo
     * data carries a realistic ledger, batches and near-expiry warnings.
     */
    public function run(): void
    {
        $manager = User::where('email', 'admin@fathimasupermarket.test')->firstOrFail();
        $products = Product::all()->keyBy('sku');

        $this->receiveGroceries($manager, $products);
        $this->receiveChilled($manager, $products);
        $this->receiveProduce($manager, $products);
        $this->writeOffSpoilage($manager, $products);
    }

    /**
     * @param  Collection<string, Product>  $products
     */
    protected function receiveGroceries(User $user, Collection $products): void
    {
        $supplier = Supplier::where('code', 'SUP-0001')->firstOrFail();

        $lines = [
            ['sku' => 'PKT-00005', 'quantity' => 30, 'unit_cost' => 54.00],
            ['sku' => 'PKT-00006', 'quantity' => 48, 'unit_cost' => 15.50, 'expires_on' => now()->addMonths(14)->toDateString()],
            ['sku' => 'PKT-00010', 'quantity' => 60, 'unit_cost' => 5.60, 'expires_on' => now()->addMonths(8)->toDateString()],
            ['sku' => 'PKT-00011', 'quantity' => 24, 'unit_cost' => 11.20, 'expires_on' => now()->addMonths(20)->toDateString()],
            ['sku' => 'PKT-00012', 'quantity' => 18, 'unit_cost' => 12.80, 'expires_on' => now()->addDays(25)->toDateString()],
            ['sku' => 'LSE-00014', 'quantity' => 250, 'unit_cost' => 6.80],
            ['sku' => 'LSE-00015', 'quantity' => 120, 'unit_cost' => 3.10],
            ['sku' => 'LSE-00016', 'quantity' => 60, 'unit_cost' => 5.20],
        ];

        $this->inventory->createStockEntry(
            attributes: [
                'type' => StockEntryType::Purchase,
                'entry_date' => now()->subDays(6)->toDateString(),
                'supplier_id' => $supplier->id,
                'invoice_number' => 'ARF-88231',
                'invoice_date' => now()->subDays(6)->toDateString(),
                'other_charges' => 150,
                'notes' => 'Weekly dry goods replenishment.',
            ],
            lines: $this->toLines($lines, $products),
            user: $user,
        );
    }

    /**
     * @param  Collection<string, Product>  $products
     */
    protected function receiveChilled(User $user, Collection $products): void
    {
        $supplier = Supplier::where('code', 'SUP-0004')->firstOrFail();

        $lines = [
            ['sku' => 'PKT-00001', 'quantity' => 90, 'unit_cost' => 7.75, 'expires_on' => now()->addDays(6)->toDateString()],
            ['sku' => 'PKT-00002', 'quantity' => 72, 'unit_cost' => 4.40, 'expires_on' => now()->addDays(9)->toDateString()],
            ['sku' => 'PKT-00013', 'quantity' => 60, 'unit_cost' => 2.00, 'expires_on' => now()->addDays(3)->toDateString()],
            ['sku' => 'PKT-00016', 'quantity' => 45, 'unit_cost' => 13.50, 'expires_on' => now()->addDays(18)->toDateString()],
            ['sku' => 'PKT-00004', 'quantity' => 24, 'unit_cost' => 14.20, 'expires_on' => now()->addMonths(4)->toDateString()],
        ];

        $this->inventory->createStockEntry(
            attributes: [
                'type' => StockEntryType::Purchase,
                'entry_date' => now()->subDay()->toDateString(),
                'supplier_id' => $supplier->id,
                'invoice_number' => 'ALM-55120',
                'invoice_date' => now()->subDay()->toDateString(),
                'notes' => 'Daily chilled delivery.',
            ],
            lines: $this->toLines($lines, $products),
            user: $user,
        );
    }

    /**
     * @param  Collection<string, Product>  $products
     */
    protected function receiveProduce(User $user, Collection $products): void
    {
        $supplier = Supplier::where('code', 'SUP-0002')->firstOrFail();

        $lines = [
            ['sku' => 'LSE-00001', 'quantity' => 60, 'unit_cost' => 3.60, 'expires_on' => now()->addDays(4)->toDateString()],
            ['sku' => 'LSE-00002', 'quantity' => 45, 'unit_cost' => 3.00, 'expires_on' => now()->addDays(5)->toDateString()],
            ['sku' => 'LSE-00003', 'quantity' => 110, 'unit_cost' => 2.60],
            ['sku' => 'LSE-00004', 'quantity' => 95, 'unit_cost' => 2.90],
            ['sku' => 'LSE-00005', 'quantity' => 14, 'unit_cost' => 6.50, 'expires_on' => now()->addDays(5)->toDateString()],
            ['sku' => 'LSE-00006', 'quantity' => 40, 'unit_cost' => 4.80, 'expires_on' => now()->addDays(4)->toDateString()],
            ['sku' => 'LSE-00007', 'quantity' => 28, 'unit_cost' => 8.20, 'expires_on' => now()->addDays(13)->toDateString()],
            ['sku' => 'LSE-00008', 'quantity' => 32, 'unit_cost' => 4.40, 'expires_on' => now()->addDays(11)->toDateString()],
            ['sku' => 'LSE-00009', 'quantity' => 3.5, 'unit_cost' => 5.50, 'expires_on' => now()->addDays(2)->toDateString()],
        ];

        $this->inventory->createStockEntry(
            attributes: [
                'type' => StockEntryType::Purchase,
                'entry_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'invoice_number' => 'WGF-4471',
                'invoice_date' => now()->toDateString(),
                'notes' => 'Morning produce load from the farm.',
            ],
            lines: $this->toLines($lines, $products),
            user: $user,
        );
    }

    /**
     * @param  Collection<string, Product>  $products
     */
    protected function writeOffSpoilage(User $user, Collection $products): void
    {
        $this->inventory->createAdjustment(
            attributes: [
                'adjustment_date' => now()->toDateString(),
                'reason' => AdjustmentReason::Wastage,
                'notes' => 'Morning shelf check — bruised and wilted produce removed.',
            ],
            lines: [
                ['product_id' => $products['LSE-00001']->id, 'direction' => 'out', 'quantity' => 3.2, 'notes' => 'Bruised tomatoes'],
                ['product_id' => $products['LSE-00006']->id, 'direction' => 'out', 'quantity' => 2.4, 'notes' => 'Over-ripe bananas'],
                ['product_id' => $products['LSE-00009']->id, 'direction' => 'out', 'quantity' => 0.5, 'notes' => 'Wilted parsley'],
            ],
            user: $user,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  Collection<string, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    protected function toLines(array $lines, Collection $products): array
    {
        return collect($lines)->map(function (array $line) use ($products): array {
            $product = $products[$line['sku']];

            return [
                ...$line,
                'product_id' => $product->id,
                'tax_percent' => (float) $product->tax_rate,
                'batch_number' => $product->track_batches ? strtoupper('B'.now()->format('ymd')) : null,
            ];
        })->all();
    }
}
