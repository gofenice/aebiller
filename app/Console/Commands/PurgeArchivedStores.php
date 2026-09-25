<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sweeps away stores archived longer ago than the keeping period. Until this
 * runs, an archived store can still be put back.
 */
#[Signature('stores:purge-archived')]
#[Description('Delete stores archived more than the keeping period ago, and all their data')]
class PurgeArchivedStores extends Command
{
    public function handle(): int
    {
        $stores = Store::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(Store::KEEP_ARCHIVED_DAYS))
            ->get();

        foreach ($stores as $store) {
            $address = $store->original_slug ?: $store->slug;

            $this->line("Purging {$store->name} ({$address})…");

            $store->forceDelete();
        }

        $this->info($stores->isEmpty()
            ? 'No archived store is old enough to purge.'
            : $stores->count().' archived store(s) purged.');

        return self::SUCCESS;
    }
}
