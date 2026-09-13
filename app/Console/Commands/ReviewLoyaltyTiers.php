<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\LoyaltyService;
use App\Support\StoreContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loyalty:review-tiers')]
#[Description('Move members up or down a tier as old spend falls out of the tier window')]
class ReviewLoyaltyTiers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LoyaltyService $loyalty): int
    {
        $changed = 0;

        foreach (Store::query()->active()->get() as $store) {
            $changed += StoreContext::runFor($store, fn (): int => $loyalty->refreshAllTiers());
        }

        $this->info("{$changed} member(s) moved to a new tier.");

        return self::SUCCESS;
    }
}
