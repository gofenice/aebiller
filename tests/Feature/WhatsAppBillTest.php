<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sending the customer their bill on WhatsApp. The link is the same public
 * page the receipt QR code points at, so nothing new is exposed.
 */
class WhatsAppBillTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Credentials live on the store, so a request re-applying the store's
     * settings keeps them rather than wiping them.
     */
    protected function enableWhatsApp(): void
    {
        $this->store->update([
            'whatsapp_token' => 'test-token',
            'whatsapp_phone_number_id' => '1234567890',
            // The bill is sent by hand in these tests.
            'whatsapp_auto_send_bill' => false,
        ]);

        $this->useStore($this->store->fresh());
    }

    protected function bill(array $overrides = []): Sale
    {
        return Sale::factory()->create([
            'customer_phone' => '0512345678',
            'grand_total' => 109.43,
            ...$overrides,
        ]);
    }

    public function test_the_bill_is_sent_and_the_message_recorded(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.XYZ']]])]);

        $sale = $this->bill();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('sales.whatsapp', $sale))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('whatsapp_messages', [
            'purpose' => 'bill',
            'status' => 'sent',
            'provider_message_id' => 'wamid.XYZ',
            'related_id' => $sale->id,
        ]);

        Http::assertSent(fn ($request): bool => $request['to'] === '966512345678'
            && $request['type'] === 'template'
            // The bill's own key travels as the button's dynamic suffix.
            && $request['template']['components'][1]['parameters'][0]['text'] === $sale->uuid);
    }

    public function test_a_refusal_from_whatsapp_is_reported_and_written_down(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not approved']], 400)]);

        $sale = $this->bill();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('sales.whatsapp', $sale))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('whatsapp_messages', [
            'purpose' => 'bill',
            'status' => 'failed',
            'error' => 'Template not approved',
        ]);
    }

    public function test_a_bill_with_no_mobile_number_is_refused_before_anything_is_sent(): void
    {
        $this->enableWhatsApp();
        Http::fake();

        $sale = $this->bill(['customer_phone' => null, 'customer_id' => null]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('sales.whatsapp', $sale))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_when_whatsapp_is_not_configured(): void
    {
        config(['services.whatsapp.token' => null, 'services.whatsapp.phone_number_id' => null]);
        Http::fake();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('sales.whatsapp', $this->bill()))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_the_members_own_number_is_used_when_the_bill_has_none(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.XYZ']]])]);

        $customer = Customer::factory()->create(['phone' => '0598765432']);
        $sale = $this->bill(['customer_phone' => null, 'customer_id' => $customer->id]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('sales.whatsapp', $sale))
            ->assertSessionHas('status');

        Http::assertSent(fn ($request): bool => $request['to'] === '966598765432');
    }

    /**
     * Shops type local numbers; WhatsApp needs international ones.
     */
    public function test_local_numbers_are_turned_into_international_ones(): void
    {
        $gateway = app(WhatsAppGateway::class);

        $this->assertSame('966512345678', $gateway->toE164('0512345678'));
        $this->assertSame('966512345678', $gateway->toE164('05 1234 5678'));
        // Already international, in either shape.
        $this->assertSame('966512345678', $gateway->toE164('+966512345678'));
        $this->assertSame('966512345678', $gateway->toE164('00966512345678'));
    }

    public function test_a_number_is_masked_before_it_is_shown_to_the_cashier(): void
    {
        $this->assertSame('••••••••5678', app(WhatsAppGateway::class)->mask('966512345678'));
    }
}
