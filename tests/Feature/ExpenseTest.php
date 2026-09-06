<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => ExpenseCategory::factory()->create()->id,
            'payee' => 'Al Nakheel Cafeteria',
            'description' => 'Tea and sugar for the staff room',
            'amount' => 86.96,
            'vat_rate' => 15,
            'payment_method' => PaymentMethod::Cash->value,
            'is_paid' => '1',
            ...$overrides,
        ];
    }

    public function test_an_expense_can_be_recorded_with_its_vat_worked_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('expenses.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();

        $this->assertSame(86.96, (float) $expense->amount);
        $this->assertSame(13.04, (float) $expense->vat_amount);
        $this->assertSame(100.00, (float) $expense->total);
        $this->assertSame($user->id, $expense->created_by);
        $this->assertStringStartsWith('EXP-'.now()->format('ym'), $expense->reference_no);
    }

    public function test_a_zero_rated_expense_carries_no_vat(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), $this->payload([
                'description' => 'Shop rent',
                'amount' => 8000,
                'vat_rate' => 0,
            ]))
            ->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();

        $this->assertSame(0.00, (float) $expense->vat_amount);
        $this->assertSame(8000.00, (float) $expense->total);
    }

    public function test_a_supplier_bill_can_be_booked_against_a_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), $this->payload([
                'supplier_id' => $supplier->id,
                'payee' => null,
                'description' => 'Weekly grocery delivery',
            ]))
            ->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();

        $this->assertSame($supplier->id, $expense->supplier_id);
        $this->assertSame($supplier->name, $expense->paid_to);
    }

    public function test_an_expense_must_say_who_was_paid(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), $this->payload(['payee' => null, 'supplier_id' => null]))
            ->assertSessionHasErrors('payee');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_an_unpaid_expense_is_tracked_as_owed(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), $this->payload(['is_paid' => '0']))
            ->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();

        $this->assertFalse($expense->is_paid);
        $this->assertNull($expense->paid_on);
        $this->assertSame(1, Expense::unpaid()->count());
    }

    public function test_a_paid_expense_defaults_its_payment_date_to_the_expense_date(): void
    {
        $date = now()->subDays(3)->toDateString();

        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), $this->payload(['expense_date' => $date, 'paid_on' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame($date, Expense::firstOrFail()->paid_on->toDateString());
    }

    public function test_a_receipt_can_be_attached(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), $this->payload([
                'attachment' => UploadedFile::fake()->image('receipt.jpg'),
            ]))
            ->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();

        $this->assertNotNull($expense->attachment_path);
        Storage::disk('public')->assertExists($expense->attachment_path);
    }

    public function test_a_future_dated_expense_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), $this->payload(['expense_date' => now()->addWeek()->toDateString()]))
            ->assertSessionHasErrors('expense_date');
    }

    public function test_the_index_totals_only_the_selected_period(): void
    {
        $user = User::factory()->create();
        $category = ExpenseCategory::factory()->create();

        Expense::factory()->for($category, 'category')->create([
            'expense_date' => now()->subMonths(3)->toDateString(), 'total' => 500, 'amount' => 500, 'vat_amount' => 0,
        ]);
        Expense::factory()->for($category, 'category')->create([
            'expense_date' => now()->toDateString(), 'total' => 120, 'amount' => 120, 'vat_amount' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('expenses.index', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary): bool => (float) $summary->total === 120.0 && (int) $summary->entries === 1);
    }

    public function test_only_a_super_admin_can_delete_an_expense(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('expenses.destroy', $expense))
            ->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('expenses.index'));

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_a_category_in_use_cannot_be_deleted(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('expense-categories.destroy', $expense->category))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('expense_categories', 1);
    }

    public function test_an_expense_can_be_edited(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('expenses.update', $expense), $this->payload([
                'expense_category_id' => $expense->expense_category_id,
                'description' => 'Corrected description',
                'amount' => 200,
                'vat_rate' => 15,
            ]))
            ->assertSessionHasNoErrors();

        $expense->refresh();

        $this->assertSame('Corrected description', $expense->description);
        $this->assertSame(230.00, (float) $expense->total);
    }
}
