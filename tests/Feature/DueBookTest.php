<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyTier;
use App\Models\Store;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Typing the shop's old due book in: each line names someone who owed money
 * before the shop billed here, and says how much.
 */
class DueBookTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        LoyaltyTier::factory()->create(['name' => 'Classic', 'min_spend' => 0, 'earn_multiplier' => 1]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function line(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->manager)->post(route('customers.due-book.store'), [
            'name' => 'Aisha Rahman',
            'phone' => '0551234567',
            'amount' => 450.50,
            'opening_due_on' => '2026-08-31',
            'opening_due_note' => 'From the old book',
            ...$overrides,
        ]);
    }

    public function test_a_new_name_is_signed_up_owing_that_amount(): void
    {
        $this->line()->assertRedirect(route('customers.due-book'))->assertSessionHas('status');

        $customer = Customer::sole();

        $this->assertSame('Aisha Rahman', $customer->name);
        $this->assertSame('450.50', $customer->opening_due);
        $this->assertSame('450.50', $customer->opening_due_outstanding);
        $this->assertSame('2026-08-31', $customer->opening_due_on->toDateString());
        $this->assertSame('From the old book', $customer->opening_due_note);

        // Signed up properly, so the till can find them by card or number.
        $this->assertNotNull($customer->activeCard);
    }

    public function test_a_number_already_on_the_books_is_topped_up_not_duplicated(): void
    {
        $existing = Customer::factory()->create(['name' => 'Aisha Rahman', 'phone' => '0551234567']);

        $this->line(['amount' => 300])->assertSessionHas('status');

        $this->assertSame(1, Customer::count());
        $this->assertSame('300.00', $existing->fresh()->opening_due_outstanding);
    }

    public function test_the_amount_and_the_number_are_both_required(): void
    {
        $this->line(['amount' => null])->assertSessionHasErrors('amount');
        $this->line(['phone' => ''])->assertSessionHasErrors('phone');
        $this->line(['name' => ''])->assertSessionHasErrors('name');

        $this->assertSame(0, Customer::count());
    }

    public function test_the_balance_cannot_be_set_below_what_was_collected(): void
    {
        $customer = Customer::factory()->create(['name' => 'Aisha Rahman', 'phone' => '0551234567']);
        app(BillingService::class)->setOpeningDue($customer, ['amount' => 500]);
        app(BillingService::class)->settleOpeningDue($customer->fresh(), ['amount' => 200], $this->manager);

        $this->line(['amount' => 150])->assertSessionHas('error');

        $this->assertSame('300.00', $customer->fresh()->opening_due_outstanding);
    }

    public function test_the_page_lists_what_has_been_entered_and_the_running_total(): void
    {
        $this->line(['name' => 'Aisha Rahman', 'phone' => '0551234567', 'amount' => 450]);
        $this->line(['name' => 'Mohammed Al Harbi', 'phone' => '0502345678', 'amount' => 120]);

        $this->actingAs($this->manager)
            ->get(route('customers.due-book'))
            ->assertOk()
            ->assertSee('Aisha Rahman')
            ->assertSee('Mohammed Al Harbi')
            ->assertSee('570.00');
    }

    public function test_only_staff_who_manage_customers_can_open_it(): void
    {
        $this->actingAs($this->manager)->get(route('customers.due-book'))->assertOk();

        auth()->logout();

        $this->get(route('customers.due-book'))->assertRedirectContains('/login');
    }

    public function test_another_store_never_sees_these_lines(): void
    {
        $this->line();

        $this->useStore(Store::factory()->create(['slug' => 'other']));

        $this->assertSame(0, Customer::count());
    }
}
