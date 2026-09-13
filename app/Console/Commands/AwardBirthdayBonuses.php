<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\LoyaltyService;
use App\Support\StoreContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loyalty:birthday-bonuses')]
#[Description('Credit the birthday bonus to members whose birthday is today')]
class AwardBirthdayBonuses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LoyaltyService $loyalty): int
    {
        $count = 0;

        foreach (Store::query()->active()->get() as $store) {
            $count += StoreContext::runFor($store, fn (): int => $loyalty->awardBirthdayBonuses());
        }

        $this->info("Birthday bonus credited to {$count} member(s).");

        return self::SUCCESS;
    }
}
