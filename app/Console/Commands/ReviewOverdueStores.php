<?php

namespace App\Console\Commands;

use App\Services\BillingCycle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:review-overdue')]
#[Description('Mark unpaid invoices overdue and close stores whose grace period has run out')]
class ReviewOverdueStores extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BillingCycle $billing): int
    {
        $result = $billing->reviewOverdue();

        $this->info("{$result['overdue']} invoice(s) marked overdue; {$result['suspended']} store(s) suspended.");

        return self::SUCCESS;
    }
}
