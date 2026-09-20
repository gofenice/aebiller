<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\StoreContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Loads a shop's handwritten stock register — transcribed to JSON — into one
 * store: a product per line, and the counted pieces as opening stock with a
 * batch per expiry date.
 */
#[Signature('register:import {file : Path to the transcribed register JSON} {--store= : Slug of the store to import into} {--dry-run : Report what would be written without writing it}')]
#[Description("Import a stock register JSON into a store's catalogue and opening stock")]
class ImportRegisterCommand extends Command
{
    /**
     * Aisle for a product, by the first keyword its name matches. The order
     * matters: the first match wins, so put the specific words first.
     *
     * @var array<string, array<int, string>>
     */
    protected array $categoryKeywords = [
        'Fresh Vegetables' => ['savala', 'tomato', 'potato', 'brinjal', 'cucumber', 'carrot', 'bitter gourd', 'capsicum', 'chilly)', 'ginger'],
        'Fresh Fruits' => ['banana', 'apple', 'orange', 'musambi', 'mango)'],
        'Herbs & Leaves' => ['curry leaves', 'coriander leaves', 'mint leaves'],
        'Dates' => ['dates'],
        'Nuts & Seeds' => ['cashew', 'badam', 'pista', 'walnut', 'peanut', 'almond', 'raisin', 'nuts', 'fig', 'seeds'],
        'Laban & Yoghurt' => ['laban', 'yoghurt', 'labneh', 'kefir', 'ayran'],
        'Fresh Milk' => ['milk', 'dano', 'cream'],
        'Cheese & Butter' => ['cheese', 'butter', 'ghee'],
        'Eggs' => ['eggs'],
        'Sliced Bread & Buns' => ['bread', 'bun', 'rusk', 'croissant', 'danish', 'puff'],
        'Cakes & Biscuits' => ['cake', 'cookies', 'mamoul', 'donut', 'swiss roll', 'biscuit', 'wafer'],
        'Tea & Coffee' => ['tea', 'coffee', 'kappi', 'koppi', 'nescafe', 'horlicks', 'boost'],
        'Juices' => ['juice', 'drink', 'frooti', 'suntop', 'moussy', 'nectar'],
        'Soft Drinks' => ['pepsi', 'cola', 'sprite', 'fanta', 'mirinda', '7up', 'mountain dew', 'kinza', 'vimto', 'barbican', 'red bull', 'sting', 'bison', 'rockstar', 'gatorade', 'soda', 'shani'],
        'Water' => ['water'],
        'Chips & Savoury' => ['chips', 'lays', 'doritos', 'cheetos', 'pringles', 'tasali', 'crisps', 'popcorn', 'pop corn', 'mixture', 'murukku', 'snack', 'pakkavada', 'kadala', 'chanachaur', 'chana chaur'],
        'Chocolates & Sweets' => ['chocolate', 'choclate', 'galaxy', 'snickers', 'twix', 'bounty', 'kit kat', 'maltesers', 'mars', 'm&m', 'ferrero', 'kinder', 'jelly', 'halwa', 'candy'],
        'Pasta & Noodles' => ['noodle', 'macaroni', 'spaghetti', 'vermicelli', 'pasta', 'indomie'],
        'Sauces & Spreads' => ['sauce', 'ketchup', 'mayonnaise', 'jam', 'peanut butter', 'nutbella', 'tahina', 'vinegar', 'paste', 'pickle', 'chammanthi', 'olive'],
        'Cooking Oils' => ['oil'],
        // Household brands come before the spice aisle, or "Ariel Powder"
        // lands next to the chilli powder.
        'Detergents' => ['detergent', 'ariel', 'tide', 'bonux', 'omo', 'ujala', 'clorox', 'bleach'],
        'Cleaning Supplies' => ['cleaner', 'harpic', 'kleenz', 'brush', 'mop', 'wiper', 'garbage', 'trash', 'scour', 'dishwash', 'wash', 'fairy', 'pril', 'drain'],
        'Ground Spices' => ['masala', 'powder', 'podi', 'kayam'],
        'Whole Spices' => ['pepper', 'cardamom', 'cloves', 'cumin', 'fennel', 'bay leaves', 'jeerakam', 'kaduku', 'uluva', 'sammak', 'yansoon', 'zathar', 'anise'],
        'Pulses & Beans' => ['dal', 'beans', 'peas', 'lentil', 'chick peas', 'parippu', 'payar', 'uzhunnu', 'mung', 'gram', 'soya bean'],
        'Sugar & Salt' => ['sugar', 'salt', 'jaggery', 'sweetener'],
        'Basmati Rice' => ['rice'],
        'Flour' => ['flour', 'maida', 'atta', 'rava', 'aval', 'wheat', 'oats', 'corn flakes', 'cereal'],
        'Kitchen Essentials' => ['spoon', 'chopper', 'dabba', 'mug', 'cup', 'kitchen', 'tissue', 'foil'],
        'Hair Care' => ['shampoo'],
        'Soaps & Body Wash' => ['soap', 'lux', 'pears', 'himalaya', 'cinthol', 'palmolive'],
        'Oral Care' => ['tooth'],
        'Personal Care' => ['diaper', 'pads', 'kotex', 'always', 'pampers', 'baby joy'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(InventoryService $inventory): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("No such file: {$path}");

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            $this->error('The register file is not valid JSON.');

            return self::FAILURE;
        }

        $store = Store::query()->where('slug', $this->option('store'))->first();

        if ($store === null) {
            $this->error('Pass --store with the slug of an existing store.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        return StoreContext::runFor($store, function () use ($rows, $inventory, $dryRun): int {
            $user = User::query()->orderBy('id')->first();

            if ($user === null) {
                $this->error('This store has no user to record the stock against.');

                return self::FAILURE;
            }

            $pieces = $this->unit('Piece', 'pc', false);
            $kilogram = $this->unit('Kilogram', 'kg', true);

            $created = 0;
            $skipped = 0;
            $batches = 0;
            $units = 0.0;

            foreach ($this->mergeRepeats($rows) as $row) {
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $size = $row['size'] ?? null;
                $displayName = $size ? "{$name} {$size}" : $name;
                $weighable = (bool) ($row['weighable'] ?? false);

                // The register is one long list with repeats across pages, so
                // the name and pack size together decide what is already in.
                $existing = Product::withTrashed()->where('name', $displayName)->first();

                if ($existing !== null) {
                    $skipped++;

                    continue;
                }

                $rowBatches = array_values(array_filter(
                    $row['batches'] ?? [],
                    fn (array $batch): bool => ((float) ($batch['qty'] ?? 0)) > 0,
                ));

                $tracksExpiry = collect($rowBatches)->contains(fn (array $batch): bool => filled($batch['expiry'] ?? null));

                if ($dryRun) {
                    $created++;
                    $batches += count($rowBatches);
                    $units += array_sum(array_column($rowBatches, 'qty'));

                    continue;
                }

                $product = Product::create([
                    'sku' => $this->nextSku(),
                    'name' => $displayName,
                    'category_id' => $this->category($name)->id,
                    'unit_id' => $weighable ? $kilogram->id : $pieces->id,
                    'is_weighable' => $weighable,
                    'track_batches' => $tracksExpiry,
                    'track_expiry' => $tracksExpiry,
                    'tax_rate' => 0,
                    'price_includes_tax' => true,
                    'cost_price' => 0,
                    'selling_price' => 0,
                    'current_stock' => 0,
                    'is_active' => true,
                    'created_by' => $user->id,
                    'description' => filled($row['serial'] ?? null) ? "Register no. {$row['serial']}" : null,
                ]);

                $created++;

                foreach ($rowBatches as $batch) {
                    $quantity = (float) $batch['qty'];

                    $inventory->recordOpeningStock(
                        product: $product,
                        quantity: $quantity,
                        user: $user,
                        expiresOn: $this->expiryDate($batch['expiry'] ?? null),
                    );

                    $batches++;
                    $units += $quantity;
                }
            }

            $this->table(
                ['Products created', 'Already present', 'Opening batches', 'Units'],
                [[$created, $skipped, $batches, rtrim(rtrim(number_format($units, 3), '0'), '.')]],
            );

            if ($dryRun) {
                $this->warn('Dry run — nothing was written.');
            }

            return self::SUCCESS;
        });
    }

    /**
     * A product written up on two pages of the register is one product with
     * both counts, not two — its batches are pooled onto the first line.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function mergeRepeats(array $rows): array
    {
        $merged = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $key = Str::lower($name.'|'.($row['size'] ?? ''));

            if (! isset($merged[$key])) {
                $merged[$key] = $row;

                continue;
            }

            $merged[$key]['batches'] = [...$merged[$key]['batches'] ?? [], ...$row['batches'] ?? []];
        }

        return array_values($merged);
    }

    /**
     * A register expiry is a month ("2027-05") or a day ("2027-05-14"). A
     * month means the goods last to the end of it.
     */
    protected function expiryDate(?string $expiry): ?string
    {
        if (blank($expiry)) {
            return null;
        }

        return substr_count($expiry, '-') === 1
            ? Carbon::createFromFormat('Y-m-d', "{$expiry}-01")->endOfMonth()->toDateString()
            : Carbon::parse($expiry)->toDateString();
    }

    protected function unit(string $name, string $code, bool $allowsDecimal): Unit
    {
        return Unit::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'allows_decimal' => $allowsDecimal, 'is_active' => true],
        );
    }

    /**
     * The aisle this product belongs on, created on first use.
     */
    protected function category(string $name): Category
    {
        $haystack = Str::lower($name);

        foreach ($this->categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $this->categoryRecord($category);
                }
            }
        }

        return $this->categoryRecord('General');
    }

    protected function categoryRecord(string $name): Category
    {
        return Category::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true],
        );
    }

    /**
     * Sequential codes so the shop can read a product's number off the label.
     */
    protected function nextSku(): string
    {
        $last = Product::withTrashed()->where('sku', 'like', 'FSM-%')->max('sku');
        $number = $last ? ((int) substr((string) $last, 4)) + 1 : 1;

        return 'FSM-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
