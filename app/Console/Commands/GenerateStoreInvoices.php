<?php

namespace App\Console\Commands;

use App\Services\BillingCycle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:generate-invoices')]
#[Description('Raise the monthly subscription invoice for every store that has come due')]
class GenerateStoreInvoices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BillingCycle $billing): int
    {
        $result = $billing->generateDueInvoices();

        $totals = collect($result['total'])
            ->map(fn (float $amount, string $currency): string => $currency.' '.number_format($amount, 2))
            ->implode(', ');

        $this->info("Raised {$result['invoices']} invoice(s)".($totals !== '' ? " totalling {$totals}." : '.'));

        return self::SUCCESS;
    }
}
