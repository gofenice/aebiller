<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Store;
use App\Models\Unit;
use App\Support\StoreContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Takes the pack size out of a product's name and puts it in the fields meant
 * for it, so "Milma Cow Ghee 500 ml" becomes "Milma Cow Ghee" sold by the
 * piece in a 500 ml pack.
 */
#[Signature('products:split-pack-size {--store= : Slug of the store to tidy} {--dry-run : Show what would change without changing it}')]
#[Description('Move the pack size out of product names into the pack size and pack unit fields')]
class SplitPackSizeFromNames extends Command
{
    /**
     * Sizes as a register writes them, mapped to the unit they are measured
     * in. Longer spellings come first so "ltr" is not read as "l".
     *
     * @var array<string, string>
     */
    protected array $unitWords = [
        'kg' => 'kg',
        'kilogram' => 'kg',
        'gm' => 'g',
        'gms' => 'g',
        'g' => 'g',
        'ltr' => 'ltr',
        'litre' => 'ltr',
        'liter' => 'ltr',
        'l' => 'ltr',
        'ml' => 'ml',
    ];

    public function handle(): int
    {
        $store = Store::query()->where('slug', $this->option('store'))->first();

        if ($store === null) {
            $this->error('Pass --store with the slug of an existing store.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        return StoreContext::runFor($store, function () use ($dryRun): int {
            $units = Unit::pluck('id', 'code');
            $changed = 0;
            $untouched = 0;
            $samples = [];

            foreach (Product::cursor() as $product) {
                $split = $this->split($product->name);

                if ($split === null) {
                    $untouched++;

                    continue;
                }

                [$name, $size, $unitCode] = $split;

                if ($name === '' || ! isset($units[$unitCode])) {
                    $untouched++;

                    continue;
                }

                if (count($samples) < 8) {
                    $samples[] = [$product->name, $name, rtrim(rtrim(number_format($size, 3), '0'), '.').' '.$unitCode];
                }

                $changed++;

                if (! $dryRun) {
                    $product->forceFill([
                        'name' => $name,
                        'pack_size' => $size,
                        'pack_unit_id' => $units[$unitCode],
                    ])->save();
                }
            }

            $this->table(['Was', 'Name', 'Pack'], $samples);
            $this->table(['Split out', 'Left alone'], [[$changed, $untouched]]);

            if ($dryRun) {
                $this->warn('Dry run — nothing was written.');
            }

            return self::SUCCESS;
        });
    }

    /**
     * A trailing measurement on the name, as [name, size, unit code].
     *
     * Counts of pieces — "30 pads", "100 tea bags" — are left where they are:
     * they say what is in the pack, not how much it holds, and the shop reads
     * them off the label.
     *
     * @return array{0: string, 1: float, 2: string}|null
     */
    protected function split(string $name): ?array
    {
        $words = implode('|', array_keys($this->unitWords));

        if (preg_match('/^(.*?)[\s,]+(\d+(?:\.\d+)?)\s*('.$words.')$/i', trim($name), $match) !== 1) {
            return null;
        }

        return [
            trim($match[1]),
            (float) $match[2],
            $this->unitWords[strtolower($match[3])],
        ];
    }
}
