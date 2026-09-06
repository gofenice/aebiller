<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Sale;
use App\Models\User;
use App\Services\ProfitLossReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitLossReportTest extends TestCase
{
    use RefreshDatabase;

    protected function report(string $from, string $to): array
    {
        return app(ProfitLossReport::class)->build(
            CarbonImmutable::parse($from),
            CarbonImmutable::parse($to),
        );
    }

    public function test_it_nets_sales_against_cost_of_goods_and_overheads(): void
    {
        Sale::factory()->create([
            'sold_at' => '2026-06-10 11:00:00',
            'subtotal_excl_vat' => 1000,
            'vat_total' => 150,
            'grand_total' => 1150,
            'cost_total' => 600,
        ]);

        Expense::factory()->create([
            'expense_date' => '2026-06-12',
            'expense_category_id' => ExpenseCategory::factory()->create(['name' => 'Rent', 'slug' => 'rent'])->id,
            'amount' => 250,
            'vat_amount' => 0,
            'total' => 250,
        ]);

        $report = $this->report('2026-06-01', '2026-06-30');

        $this->assertSame(1000.0, $report['sales']['revenue']);
        $this->assertSame(600.0, $report['sales']['cogs']);
        $this->assertSame(400.0, $report['grossProfit']);
        $this->assertSame(250.0, $report['operatingExpenses']);
        $this->assertSame(150.0, $report['netProfit']);
        $this->assertSame(15.0, $report['margins']['net']);
    }

    public function test_goods_bought_for_resale_are_kept_out_of_operating_expenses(): void
    {
        $goods = ExpenseCategory::factory()->create([
            'name' => 'Goods purchase',
            'slug' => ProfitLossReport::GOODS_PURCHASE_SLUG,
        ]);

        Expense::factory()->create([
            'expense_date' => '2026-06-05',
            'expense_category_id' => $goods->id,
            'amount' => 5000,
            'vat_amount' => 750,
            'total' => 5750,
        ]);

        $report = $this->report('2026-06-01', '2026-06-30');

        $this->assertSame(0.0, $report['operatingExpenses']);
        $this->assertSame(5750.0, $report['goodsPurchases']['total']);
        $this->assertSame(0.0, $report['netProfit']);
    }

    public function test_voided_bills_and_activity_outside_the_period_are_ignored(): void
    {
        Sale::factory()->voided()->create([
            'sold_at' => '2026-06-10 11:00:00',
            'subtotal_excl_vat' => 900,
            'cost_total' => 400,
        ]);

        Sale::factory()->create([
            'sold_at' => '2026-07-02 11:00:00',
            'subtotal_excl_vat' => 800,
            'cost_total' => 300,
        ]);

        Expense::factory()->create(['expense_date' => '2026-07-02', 'amount' => 100, 'total' => 115]);

        $report = $this->report('2026-06-01', '2026-06-30');

        $this->assertSame(0, $report['sales']['bills']);
        $this->assertSame(0.0, $report['grossProfit']);
        $this->assertSame(0.0, $report['operatingExpenses']);
    }

    public function test_the_daily_series_covers_every_day_in_the_period(): void
    {
        Sale::factory()->create([
            'sold_at' => '2026-06-02 09:00:00',
            'subtotal_excl_vat' => 500,
            'cost_total' => 200,
        ]);

        Expense::factory()->create(['expense_date' => '2026-06-03', 'amount' => 120, 'vat_amount' => 18, 'total' => 138]);

        $report = $this->report('2026-06-01', '2026-06-04');

        $this->assertFalse($report['groupedByMonth']);
        $this->assertCount(4, $report['series']);
        $this->assertSame(500.0, $report['series'][1]['revenue']);
        $this->assertSame(300.0, $report['series'][1]['profit']);
        $this->assertSame(120.0, $report['series'][2]['expenses']);
        $this->assertSame(-120.0, $report['series'][2]['profit']);
    }

    public function test_long_periods_are_rolled_up_into_months(): void
    {
        $report = $this->report('2026-01-01', '2026-06-30');

        $this->assertTrue($report['groupedByMonth']);
        $this->assertCount(6, $report['series']);
    }

    public function test_the_page_reports_the_chosen_period(): void
    {
        Sale::factory()->create([
            'sold_at' => now()->startOfMonth()->addDay()->setTime(10, 0),
            'subtotal_excl_vat' => 1000,
            'vat_total' => 150,
            'grand_total' => 1150,
            'cost_total' => 400,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('reports.profit-loss', ['period' => 'this-month']))
            ->assertOk()
            ->assertSee('Income &amp; expenses', false)
            ->assertSee('600.00');
    }

    public function test_a_custom_range_wins_over_the_preset_and_is_reordered_if_reversed(): void
    {
        Sale::factory()->create([
            'sold_at' => '2026-03-15 10:00:00',
            'subtotal_excl_vat' => 700,
            'cost_total' => 200,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('reports.profit-loss', ['period' => 'today', 'from' => '2026-03-31', 'to' => '2026-03-01']))
            ->assertOk()
            ->assertSee('1 Mar 2026')
            ->assertSee('31 Mar 2026')
            ->assertSee('500.00');
    }

    public function test_an_inactive_user_cannot_open_the_report(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->get(route('reports.profit-loss'))
            ->assertForbidden();
    }
}
