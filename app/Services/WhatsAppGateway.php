<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sending WhatsApp messages through Meta's Cloud API.
 *
 * Every send is written down, whether it worked or not: a shop needs to be
 * able to say what was sent to a customer, and a redemption that could not be
 * confirmed has to leave a trace of why.
 *
 * Nothing here throws on a delivery failure. A message that will not send must
 * never take the till down with it — the caller reads the status and decides.
 */
class WhatsAppGateway
{
    public function enabled(): bool
    {
        return filled(config('services.whatsapp.token'))
            && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * Send the customer a link to their bill.
     */
    public function sendBill(Sale $sale, ?User $sentBy = null): ?WhatsAppMessage
    {
        $to = $this->recipientFor($sale);

        if ($to === null) {
            return null;
        }

        return $this->sendTemplate(
            to: $to,
            template: config('services.whatsapp.bill_template'),
            bodyParams: [
                $sale->customer_name ?: 'there',
                $sale->invoice_no,
                number_format((float) $sale->grand_total, 2),
                config('app.name'),
            ],
            // Meta appends this to the template's button URL, so only the
            // changing part of the address travels.
            buttonParam: $sale->uuid,
            purpose: 'bill',
            related: $sale,
            sentBy: $sentBy,
        );
    }

    /**
     * Send the code that authorises a redemption. Authentication templates put
     * the same code in the body and behind the "copy code" button.
     */
    public function sendOtp(string $to, string $code, ?Customer $customer = null, ?User $sentBy = null): ?WhatsAppMessage
    {
        return $this->sendTemplate(
            to: $to,
            template: config('services.whatsapp.otp_template'),
            bodyParams: [$code],
            buttonParam: $code,
            purpose: 'otp',
            related: $customer,
            sentBy: $sentBy,
        );
    }

    /**
     * @param  array<int, string>  $bodyParams
     */
    public function sendTemplate(
        string $to,
        string $template,
        array $bodyParams = [],
        ?string $buttonParam = null,
        string $purpose = 'bill',
        ?Model $related = null,
        ?User $sentBy = null,
    ): ?WhatsAppMessage {
        if (! $this->enabled()) {
            return null;
        }

        $components = [[
            'type' => 'body',
            'parameters' => array_map(
                fn (string $value): array => ['type' => 'text', 'text' => $value],
                array_values($bodyParams),
            ),
        ]];

        if ($buttonParam !== null) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $buttonParam]],
            ];
        }

        $message = new WhatsAppMessage([
            'to' => $to,
            'purpose' => $purpose,
            'template' => $template,
            'sent_by' => $sentBy?->id,
        ]);

        if ($related !== null) {
            $message->related()->associate($related);
        }

        try {
            $response = $this->request()->post($this->url(), [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => config('services.whatsapp.language')],
                    'components' => $components,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('WhatsApp send failed', ['purpose' => $purpose, 'error' => $exception->getMessage()]);

            $message->fill(['status' => 'failed', 'error' => $exception->getMessage()])->save();

            return $message;
        }

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();

            Log::warning('WhatsApp send rejected', ['purpose' => $purpose, 'error' => $error]);

            $message->fill(['status' => 'failed', 'error' => (string) $error])->save();

            return $message;
        }

        $message->fill([
            'status' => 'sent',
            'provider_message_id' => $response->json('messages.0.id'),
            'sent_at' => now(),
        ])->save();

        return $message;
    }

    /**
     * The number to send a bill to: the one typed on the bill, else the
     * member's own.
     */
    public function recipientFor(Sale $sale): ?string
    {
        $phone = $sale->customer_phone ?: $sale->customer?->phone;

        return blank($phone) ? null : $this->toE164($phone);
    }

    /**
     * WhatsApp wants a full international number with no punctuation. Shops
     * type local numbers, so the shop's own dialling code fills the gap.
     */
    public function toE164(string $phone, ?string $countryCode = null): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // 00 971 ... is the same as +971 ...
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        $code = ltrim((string) ($countryCode
            ?: StoreContext::get()?->phone_country_code
            ?: config('services.whatsapp.default_country_code')), '+');

        // Already carries its country code.
        if ($code !== '' && str_starts_with($digits, $code) && strlen($digits) > 10) {
            return $digits;
        }

        return $code.ltrim($digits, '0');
    }

    /**
     * Hide the middle of a number before showing it to the cashier, so they
     * can confirm they are texting the right member without reading it out.
     */
    public function mask(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return str_repeat('•', strlen($digits));
        }

        return str_repeat('•', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    protected function request(): PendingRequest
    {
        return Http::withToken(config('services.whatsapp.token'))
            ->acceptJson()
            ->timeout(15);
    }

    protected function url(): string
    {
        return rtrim((string) config('services.whatsapp.base_url'), '/')
            .'/'.config('services.whatsapp.api_version')
            .'/'.config('services.whatsapp.phone_number_id').'/messages';
    }
}
