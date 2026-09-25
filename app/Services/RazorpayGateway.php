<?php

namespace App\Services;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreInvoice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Razorpay, spoken to over its REST API — no SDK, so there is nothing extra to
 * keep up to date.
 *
 * Two ways to pay: a one-off payment link per invoice, or a subscription that
 * keeps a card on file and charges it each period. Either way Razorpay tells
 * us it was paid twice over — the browser callback and the webhook — and both
 * are signed, so a closed browser still settles the invoice.
 */
class RazorpayGateway
{
    public function enabled(): bool
    {
        return filled($this->key()) && filled($this->secret());
    }

    /**
     * The link a store pays one invoice on. An unpaid link already raised is reused.
     */
    public function paymentLinkFor(StoreInvoice $invoice, string $callbackUrl): string
    {
        $this->ensureEnabled();

        if (filled($invoice->razorpay_short_url)) {
            return $invoice->razorpay_short_url;
        }

        $store = $invoice->store;

        $response = $this->request()->post($this->url('/payment_links'), [
            // Razorpay counts in the smallest unit of the currency.
            'amount' => $this->subunits((float) $invoice->outstanding()),
            'currency' => $invoice->currency_code,
            'description' => config('tenancy.platform_name').' — '.$invoice->periodLabel().' ('.$invoice->number.')',
            'reference_id' => $invoice->number,
            'customer' => $this->customerFor($store),
            // The shop is already looking at the screen; no need to chase them.
            'notify' => ['email' => false, 'sms' => false],
            'reminder_enable' => false,
            'callback_url' => $callbackUrl,
            'callback_method' => 'get',
        ]);

        if ($response->failed()) {
            Log::error('Razorpay payment link failed', ['invoice' => $invoice->number, 'body' => $response->body()]);

            throw new RuntimeException('Razorpay could not create the payment link. Please try again shortly.');
        }

        $invoice->update([
            'razorpay_payment_link_id' => $response->json('id'),
            'razorpay_short_url' => $response->json('short_url'),
        ]);

        return $response->json('short_url');
    }

    /**
     * Razorpay keeps its own copy of a plan. Created once, then remembered.
     *
     * Razorpay fixes the amount, the currency and the interval on that copy, so
     * a plan sold in several currencies on two periods needs one per
     * combination: a shop paying yearly in riyals must not be put on the
     * monthly dollar one.
     */
    public function remotePlanFor(Plan $plan, ?string $currency = null, ?BillingPeriod $period = null): string
    {
        $this->ensureEnabled();

        $period ??= $plan->billing_period;
        // Whether the charge itself is yearly — not whether a monthly plan
        // could be upgraded to yearly. A plan already priced by the year is
        // charged yearly too, and amountIn() decides about the discount.
        $yearly = $period === BillingPeriod::Yearly;
        $price = $plan->amountIn($currency, $period);
        $column = $yearly ? 'razorpay_yearly_plan_id' : 'razorpay_plan_id';

        // The base price is remembered on the plan; every other currency on the
        // row that carries its price.
        $holder = $price['is_base']
            ? $plan
            : $plan->prices()->where('currency_code', $price['currency'])->firstOrFail();

        if (filled($holder->{$column})) {
            return $holder->{$column};
        }

        $response = $this->request()->post($this->url('/plans'), [
            'period' => $yearly ? 'yearly' : 'monthly',
            'interval' => 1,
            'item' => [
                'name' => config('tenancy.platform_name').' — '.$plan->name
                    .' ('.$price['currency'].', '.($yearly ? 'yearly' : 'monthly').')',
                'amount' => $this->subunits($price['amount']),
                'currency' => $price['currency'],
            ],
        ]);

        if ($response->failed()) {
            Log::error('Razorpay plan failed', [
                'plan' => $plan->slug,
                'currency' => $price['currency'],
                'period' => $yearly ? 'yearly' : 'monthly',
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Razorpay could not set that plan up. Please try again shortly.');
        }

        $holder->update([$column => $response->json('id')]);

        return $response->json('id');
    }

    /**
     * Start a subscription: the shopkeeper authorises a card once on
     * Razorpay's page, and every period after that is charged automatically.
     *
     * @return string the page to send them to
     */
    public function startSubscription(Store $store): string
    {
        $this->ensureEnabled();

        $plan = $store->plan;

        if ($plan === null || ! $store->billingPeriod()->isRecurring()) {
            throw new RuntimeException('Automatic payment needs a monthly or yearly plan.');
        }

        $response = $this->request()->post($this->url('/subscriptions'), [
            'plan_id' => $this->remotePlanFor($plan, $store->billedCurrency(), $store->billingPeriod()),
            // Razorpay wants a finite number of cycles; ten years of them.
            'total_count' => $store->billingPeriod() === BillingPeriod::Yearly ? 10 : 120,
            'customer_notify' => 1,
            'notes' => ['store' => $store->slug],
        ]);

        if ($response->failed()) {
            Log::error('Razorpay subscription failed', ['store' => $store->slug, 'body' => $response->body()]);

            throw new RuntimeException('Razorpay could not start the automatic payment. Please try again shortly.');
        }

        $store->update([
            'razorpay_subscription_id' => $response->json('id'),
            'razorpay_subscription_status' => $response->json('status', 'created'),
        ]);

        return $response->json('short_url');
    }

    /**
     * Stop charging the card. The shop then pays invoice by invoice again.
     */
    public function cancelSubscription(Store $store): void
    {
        $this->ensureEnabled();

        if (blank($store->razorpay_subscription_id)) {
            return;
        }

        $response = $this->request()->post(
            $this->url("/subscriptions/{$store->razorpay_subscription_id}/cancel"),
            ['cancel_at_cycle_end' => 0],
        );

        if ($response->failed()) {
            Log::warning('Razorpay subscription cancel failed', ['store' => $store->slug, 'body' => $response->body()]);
        }

        $store->update([
            'auto_charge_enabled' => false,
            'razorpay_subscription_status' => 'cancelled',
        ]);
    }

    /**
     * Check the signature Razorpay adds when it sends the shopkeeper back.
     *
     * @param  array<string, mixed>  $params
     */
    public function callbackIsGenuine(array $params): bool
    {
        $signature = (string) ($params['razorpay_signature'] ?? '');

        if ($signature === '' || ! $this->enabled()) {
            return false;
        }

        $payload = implode('|', [
            $params['razorpay_payment_link_id'] ?? '',
            $params['razorpay_payment_link_reference_id'] ?? '',
            $params['razorpay_payment_link_status'] ?? '',
            $params['razorpay_payment_id'] ?? '',
        ]);

        return hash_equals(hash_hmac('sha256', $payload, $this->secret()), $signature);
    }

    /**
     * Check the signature on a webhook. Signed with the webhook secret, which
     * is a different secret from the API one.
     */
    public function webhookIsGenuine(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        if ($secret === '' || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    protected function ensureEnabled(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Online payment is not set up yet.');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function customerFor(?Store $store): array
    {
        return array_filter([
            'name' => $store?->owner_name ?: $store?->name,
            'email' => $store?->owner_email,
            'contact' => $store?->owner_phone,
        ]);
    }

    protected function subunits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Ask Razorpay whether these keys are accepted, without moving money.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function ping(): array
    {
        try {
            $response = $this->request()->timeout(15)->get($this->url('/plans'), ['count' => 1]);
        } catch (\Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }

        if ($response->successful()) {
            return ['ok' => true, 'error' => null];
        }

        return [
            'ok' => false,
            'error' => (string) ($response->json('error.description') ?? 'HTTP '.$response->status()),
        ];
    }

    protected function request(): PendingRequest
    {
        return Http::withBasicAuth($this->key(), $this->secret())->acceptJson();
    }

    protected function key(): ?string
    {
        return config('services.razorpay.key');
    }

    protected function secret(): string
    {
        return (string) config('services.razorpay.secret');
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('services.razorpay.base_url'), '/').$path;
    }
}
