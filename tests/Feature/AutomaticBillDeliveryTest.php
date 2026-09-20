<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Jobs\SendBillOnWhatsApp;
use App\Models\PlatformUser;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The customer gets their bill on WhatsApp as soon as the payment is taken,
 * sent from the shop's own WhatsApp Business number.
 */
class AutomaticBillDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create();
    }

    /**
     * Give the store in context its own WhatsApp credentials, the way the
     * platform admin saves them.
     */
    protected function credentialsOnStore(array $overrides = []): void
    {
        $this->store->update([
            'whatsapp_phone_number_id' => '555000111',
            'whatsapp_token' => 'shop-own-token',
            'whatsapp_auto_send_bill' => true,
            ...$overrides,
        ]);

        $this->useStore($this->store->fresh());
    }

    protected function sellTo(?string $phone): Sale
    {
        $product = Product::factory()->create([
            'current_stock' => 50,
            'selling_price' => 11.50,
            'tax_rate' => 15,
            'price_includes_tax' => true,
        ]);

        $this->actingAs($this->cashier)->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_paid' => 50,
            'customer_phone' => $phone,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        return Sale::latest('id')->firstOrFail();
    }

    public function test_paying_queues_the_bill_to_the_number_on_it(): void
    {
        Queue::fake();
        $this->credentialsOnStore();

        $sale = $this->sellTo('0512345678');

        Queue::assertPushed(
            SendBillOnWhatsApp::class,
            fn (SendBillOnWhatsApp $job): bool => $job->saleId === $sale->id && $job->storeId === $this->store->id,
        );
    }

    public function test_a_bill_with_no_number_is_not_queued(): void
    {
        Queue::fake();
        $this->credentialsOnStore();

        $this->sellTo(null);

        Queue::assertNothingPushed();
    }

    public function test_a_shop_that_turned_it_off_sends_nothing(): void
    {
        Queue::fake();
        $this->credentialsOnStore(['whatsapp_auto_send_bill' => false]);

        $this->sellTo('0512345678');

        Queue::assertNothingPushed();
    }

    public function test_a_shop_without_credentials_sends_nothing(): void
    {
        Queue::fake();
        config(['services.whatsapp.token' => null, 'services.whatsapp.phone_number_id' => null]);

        $this->sellTo('0512345678');

        Queue::assertNothingPushed();
    }

    public function test_the_queued_job_sends_from_the_store_s_own_number(): void
    {
        // Held on the queue, so the send under test is the job's own.
        Queue::fake();
        $this->credentialsOnStore();
        $sale = $this->sellTo('0512345678');

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.AUTO']]])]);

        (new SendBillOnWhatsApp($this->store->id, $sale->id))->handle(app(WhatsAppGateway::class));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '555000111')
            && $request->hasHeader('Authorization', 'Bearer shop-own-token')
            && $request['to'] === '966512345678');

        $this->assertDatabaseHas('whatsapp_messages', [
            'purpose' => 'bill',
            'status' => 'sent',
            'related_id' => $sale->id,
        ]);
    }

    public function test_a_voided_bill_is_never_sent(): void
    {
        Queue::fake();
        $this->credentialsOnStore();

        $sale = $this->sellTo('0512345678');
        $sale->update(['status' => SaleStatus::Voided]);

        Http::fake();

        (new SendBillOnWhatsApp($this->store->id, $sale->id))->handle(app(WhatsAppGateway::class));

        Http::assertNothingSent();
    }

    public function test_one_store_s_credentials_do_not_leak_into_another(): void
    {
        $this->credentialsOnStore();

        $other = Store::factory()->create(['slug' => 'other']);
        $this->useStore($other);

        $this->assertFalse($other->hasWhatsApp());
        $this->assertNull(config('services.whatsapp.phone_number_id'));
    }

    public function test_the_platform_admin_saves_the_keys_on_the_store(): void
    {
        $store = Store::factory()->create(['slug' => 'al-noor', 'name' => 'Al Noor Market']);
        $url = 'http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain');

        $this->actingAs(PlatformUser::factory()->create(), 'platform')
            ->put($url.'/stores/'.$store->slug, [
                'name' => $store->name,
                'slug' => $store->slug,
                'owner_name' => 'Khalid',
                'owner_email' => 'khalid@example.com',
                'currency_code' => 'AED',
                'currency_symbol' => 'AED ',
                'timezone' => 'Asia/Dubai',
                'expiry_alert_days' => 30,
                'whatsapp_phone_number_id' => '999888777',
                'whatsapp_token' => 'a-very-secret-token',
                'whatsapp_bill_template' => 'bill_copy_ar',
                'whatsapp_auto_send_bill' => '1',
            ])
            ->assertSessionHasNoErrors();

        $store->refresh();

        $this->assertSame('999888777', $store->whatsapp_phone_number_id);
        $this->assertSame('a-very-secret-token', $store->whatsapp_token);
        $this->assertSame('bill_copy_ar', $store->whatsapp_bill_template);
        $this->assertTrue($store->whatsapp_auto_send_bill);

        // Stored encrypted, so the raw column never reads back as the token.
        $this->assertNotSame('a-very-secret-token', $store->getRawOriginal('whatsapp_token'));
    }

    public function test_leaving_the_token_blank_keeps_the_saved_one(): void
    {
        $store = Store::factory()->create([
            'slug' => 'al-noor',
            'whatsapp_phone_number_id' => '999888777',
            'whatsapp_token' => 'the-original-token',
        ]);
        $url = 'http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain');

        $this->actingAs(PlatformUser::factory()->create(), 'platform')
            ->put($url.'/stores/'.$store->slug, [
                'name' => $store->name,
                'slug' => $store->slug,
                'owner_name' => 'Khalid',
                'owner_email' => 'khalid@example.com',
                'currency_code' => 'AED',
                'currency_symbol' => 'AED ',
                'timezone' => 'Asia/Dubai',
                'expiry_alert_days' => 30,
                'whatsapp_phone_number_id' => '999888777',
                'whatsapp_token' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('the-original-token', $store->fresh()->whatsapp_token);
    }

    public function test_the_saved_token_is_never_written_into_the_form(): void
    {
        $store = Store::factory()->create(['slug' => 'al-noor', 'whatsapp_token' => 'the-original-token']);
        $url = 'http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain');

        $this->actingAs(PlatformUser::factory()->create(), 'platform')
            ->get($url.'/stores/'.$store->slug.'/edit')
            ->assertOk()
            ->assertSee('WhatsApp Business')
            ->assertDontSee('the-original-token');
    }
}
