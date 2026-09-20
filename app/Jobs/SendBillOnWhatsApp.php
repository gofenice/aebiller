<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Models\Store;
use App\Services\WhatsAppGateway;
use App\Support\StoreContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends the customer their bill once the payment is taken.
 *
 * This runs off the till: WhatsApp being slow, or down, must never hold up the
 * next customer in the queue.
 */
class SendBillOnWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  int  $storeId  The sale's store, since a queued job has no subdomain to read it from.
     */
    public function __construct(public int $storeId, public int $saleId) {}

    /**
     * Wait a little longer after each failure — a number that is momentarily
     * unreachable is usually reachable a minute later.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(WhatsAppGateway $whatsapp): void
    {
        $store = Store::find($this->storeId);

        if ($store === null) {
            return;
        }

        StoreContext::runFor($store, function () use ($whatsapp): void {
            $sale = Sale::with('customer')->find($this->saleId);

            if ($sale === null || $sale->isVoided() || ! $whatsapp->enabled()) {
                return;
            }

            $whatsapp->sendBill($sale);
        });
    }
}
