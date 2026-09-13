<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\LoyaltyService;
use App\Support\StoreContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loyalty:expire-points')]
#[Description('Lapse loyalty points that have passed their expiry date')]
class ExpireLoyaltyPoints extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LoyaltyService $loyalty): int
    {
        $members = 0;
        $points = 0;

        // Every store keeps its own points, so each is run in its own context.
        foreach (Store::query()->active()->get() as $store) {
            $result = StoreContext::runFor($store, fn (): array => $loyalty->expirePoints());

            $members += $result['members'];
            $points += $result['points'];
        }

        $this->info(sprintf('Expired %s point(s) across %d member(s).', number_format($points), $members));

        return self::SUCCESS;
    }
}
