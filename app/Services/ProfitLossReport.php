<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Income against outgoings for a chosen period.
 *
 * Two things are deliberately kept apart. Goods bought for resale are already
 * charged against profit as cost of sales when the goods leave the shelf, so
 * counting the supplier bill as an expense as well would subtract the same
 * money twice. Everything else — rent, wages, power — is an overhead of the
 * period it falls in.
 */
class ProfitLossReport
{
    /**
     * Expenses in this category are stock buying, not an overhead.
     */
    public const GOODS_PURCHASE_SLUG = 'goods-purchase';

    /**
     * The ready-made periods offered on the report screen.
     *
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this-week' => 'This week',
            'this-month' => 'This month',
            'last-month' => 'Last month',
            'last-30' => 'Last 30 days',
            'this-year' => 'This year',
        ];
    }

    /**
     * Turn a preset name into its date range.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(string $preset): array
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'this-week' => [$today->startOfWeek(), $today],
            'last-month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last-30' => [$today->subDays(29), $today],
            'this-year' => [$today->startOfYear(), $today],
            default => [$today->startOfMonth(), $today],
        };
    }

    /**
     * @return array{
     *     sales: array{bills: int, gross: float, revenue: float, vat: float, cogs: float, discounts: float},
     *     grossProfit: float,
     *     operatingExpenses: float,
     *     netProfit: float,
     *     margins: array{gross: float, net: float},
     *     vatPosition: float,
     *     expenseCategories: Collection<int, object>,
     *     goodsPurchases: array{entries: int, net: float, vat: float, total: float},
     *     expenseTotals: array{entries: int, net: float, vat: float, total: float, unpaid: float},
     *     series: Collection<int, array{label: string, revenue: float, expenses: float, profit: float}>,
     *     groupedByMonth: bool
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = $this->sales($from, $to);
        $categories = $this->expensesByCategory($from, $to);

        $goodsRows = $categories->where('slug', self::GOODS_PURCHASE_SLUG);
        $overheadRows = $categories->where('slug', '!=', self::GOODS_PURCHASE_SLUG)->values();

        $operatingExpenses = round((float) $overheadRows->sum('net'), 2);
        $grossProfit = round($sales['revenue'] - $sales['cogs'], 2);
        $netProfit = round($grossProfit - $operatingExpenses, 2);

        return [
            'sales' => $sales,
            'grossProfit' => $grossProfit,
            'operatingExpenses' => $operatingExpenses,
            'netProfit' => $netProfit,
            'margins' => [
                'gross' => $sales['revenue'] > 0 ? round($grossProfit / $sales['revenue'] * 100, 1) : 0.0,
                'net' => $sales['revenue'] > 0 ? round($netProfit / $sales['revenue'] * 100, 1) : 0.0,
            ],
            // Output VAT taken at the till less the input VAT on purchases.
            'vatPosition' => round($sales['vat'] - (float) $categories->sum('vat'), 2),
            'expenseCategories' => $overheadRows,
            'goodsPurchases' => [
                'entries' => (int) $goodsRows->sum('entries'),
                'net' => round((float) $goodsRows->sum('net'), 2),
                'vat' => round((float) $goodsRows->sum('vat'), 2),
                'total' => round((float) $goodsRows->sum('total'), 2),
            ],
            'expenseTotals' => [
                'entries' => (int) $categories->sum('entries'),
                'net' => round((float) $categories->sum('net'), 2),
                'vat' => round((float) $categories->sum('vat'), 2),
                'total' => round((float) $categories->sum('total'), 2),
                'unpaid' => round((float) $categories->sum('unpaid'), 2),
            ],
            ...$this->series($from, $to),
        ];
    }

    /**
     * Completed bills only — a voided bill never happened.
     *
     * @return array{bills: int, gross: float, revenue: float, vat: float, cogs: float, discounts: float}
     */
    protected function sales(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = Sale::query()
            ->completed()
            ->whereBetween('sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->select(
                DB::raw('count(*) as bills'),
                DB::raw('coalesce(sum(grand_total), 0) as gross'),
                DB::raw('coalesce(sum(subtotal_excl_vat), 0) as revenue'),
                DB::raw('coalesce(sum(vat_total), 0) as vat'),
                DB::raw('coalesce(sum(cost_total), 0) as cogs'),
                DB::raw('coalesce(sum(line_discount_total + bill_discount), 0) as discounts'),
            )
            ->first();

        return [
            'bills' => (int) $row->bills,
            'gross' => round((float) $row->gross, 2),
            'revenue' => round((float) $row->revenue, 2),
            'vat' => round((float) $row->vat, 2),
            'cogs' => round((float) $row->cogs, 2),
            'discounts' => round((float) $row->discounts, 2),
        ];
    }

    /**
     * Expenses in the period, one row per category. Amounts are net of VAT
     * because input VAT is reclaimed rather than borne by the shop.
     *
     * @return Collection<int, object>
     */
    protected function expensesByCategory(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()])
            ->select(
                'expense_categories.id',
                'expense_categories.name',
                'expense_categories.slug',
                DB::raw('count(*) as entries'),
                DB::raw('coalesce(sum(expenses.amount), 0) as net'),
                DB::raw('coalesce(sum(expenses.vat_amount), 0) as vat'),
                DB::raw('coalesce(sum(expenses.total), 0) as total'),
                DB::raw('coalesce(sum(case when expenses.is_paid = 0 then expenses.total else 0 end), 0) as unpaid'),
            )
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.slug')
            ->orderByDesc('net')
            ->get();
    }

    /**
     * Revenue against overheads over time. Long ranges are rolled up into
     * months so the table stays readable.
     *
     * @return array{series: Collection<int, array{label: string, revenue: float, expenses: float, profit: float}>, groupedByMonth: bool}
     */
    protected function series(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byMonth = $from->diffInDays($to) > 92;

        $daily = Sale::query()
            ->completed()
            ->whereBetween('sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->select(
                DB::raw('date(sold_at) as day'),
                DB::raw('coalesce(sum(subtotal_excl_vat), 0) as revenue'),
                DB::raw('coalesce(sum(subtotal_excl_vat - cost_total), 0) as profit'),
            )
            ->groupBy(DB::raw('date(sold_at)'))
            ->get()
            ->keyBy('day');

        $overheads = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expense_categories.slug', '!=', self::GOODS_PURCHASE_SLUG)
            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()])
            ->select(
                DB::raw('date(expenses.expense_date) as day'),
                DB::raw('coalesce(sum(expenses.amount), 0) as spent'),
            )
            ->groupBy(DB::raw('date(expenses.expense_date)'))
            ->pluck('spent', 'day');

        $buckets = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $key = $date->toDateString();
            $bucket = $byMonth ? $date->format('Y-m') : $key;

            $buckets[$bucket] ??= [
                'label' => $byMonth ? $date->format('M Y') : $date->format('D j M'),
                'revenue' => 0.0,
                'expenses' => 0.0,
                'profit' => 0.0,
            ];

            $spent = (float) ($overheads[$key] ?? 0);

            $buckets[$bucket]['revenue'] += (float) ($daily[$key]->revenue ?? 0);
            $buckets[$bucket]['expenses'] += $spent;
            $buckets[$bucket]['profit'] += (float) ($daily[$key]->profit ?? 0) - $spent;
        }

        return [
            'series' => collect($buckets)->values()->map(fn (array $row): array => [
                ...$row,
                'revenue' => round($row['revenue'], 2),
                'expenses' => round($row['expenses'], 2),
                'profit' => round($row['profit'], 2),
            ]),
            'groupedByMonth' => $byMonth,
        ];
    }
}
