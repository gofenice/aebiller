<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pages a customer — and a payment provider — looks for on the public
 * site before money changes hands.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function site(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_domain').$path;
    }

    public function test_the_terms_page_reads_as_an_agreement(): void
    {
        $this->get($this->site('/terms'))
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Trial, fees and payment')
            ->assertSee('Ending the agreement')
            ->assertSee('Last updated');
    }

    public function test_the_privacy_page_says_what_is_collected(): void
    {
        $this->get($this->site('/privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Payment details')
            ->assertSee('Razorpay')
            ->assertSee('WhatsApp');
    }

    public function test_the_refund_page_covers_cancelling_and_refunds(): void
    {
        $this->get($this->site('/refunds'))
            ->assertOk()
            ->assertSee('Refund &amp; Cancellation Policy', false)
            ->assertSee('Cancelling')
            ->assertSee('How a refund is paid');
    }

    public function test_the_contact_page_shows_how_to_reach_the_business(): void
    {
        config(['tenancy.legal.email' => 'hello@example.test', 'tenancy.legal.phone' => '+971 50 000 0000']);

        $this->get($this->site('/contact'))
            ->assertOk()
            ->assertSee('Contact Us')
            ->assertSee('hello@example.test')
            ->assertSee('+971 50 000 0000');
    }

    public function test_details_left_unset_are_left_off_rather_than_invented(): void
    {
        config([
            'tenancy.legal.phone' => null,
            'tenancy.legal.address' => null,
            'tenancy.legal.entity' => null,
        ]);

        $response = $this->get($this->site('/contact'))->assertOk();

        // Falls back to the platform's name rather than showing a blank.
        $response->assertSee(config('tenancy.platform_name'));
        $response->assertDontSee('Address');
    }

    public function test_the_registered_entity_is_used_when_it_is_set(): void
    {
        config(['tenancy.legal.entity' => 'Gofenice Technologies FZ-LLC']);

        $this->get($this->site('/terms'))
            ->assertOk()
            ->assertSee('Gofenice Technologies FZ-LLC');
    }

    public function test_every_page_links_to_the_others(): void
    {
        foreach (['/terms', '/privacy', '/refunds', '/contact'] as $path) {
            $this->get($this->site($path))
                ->assertOk()
                ->assertSee($this->site('/terms'))
                ->assertSee($this->site('/privacy'))
                ->assertSee($this->site('/refunds'))
                ->assertSee($this->site('/contact'));
        }
    }

    public function test_the_landing_page_footer_carries_the_links(): void
    {
        $this->get($this->site('/'))
            ->assertOk()
            ->assertSee($this->site('/terms'))
            ->assertSee($this->site('/privacy'))
            ->assertSee($this->site('/refunds'))
            ->assertSee($this->site('/contact'));
    }
}
