<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\StockEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReferenceNumbers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpenseSeeder extends Seeder
{
    public function __construct(protected ReferenceNumbers $references) {}

    /**
     * The categories a supermarket books its outgoings against, plus a month
     * of realistic spending so the screens have something to show.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Goods purchase', 'description' => 'Supplier bills for stock received'],
            ['name' => 'Rent', 'description' => 'Shop and storeroom rent'],
            ['name' => 'Electricity & water', 'description' => 'Utility bills'],
            ['name' => 'Salaries & wages', 'description' => 'Staff pay and allowances'],
            ['name' => 'Staff refreshments', 'description' => 'Tea, coffee and staff room supplies'],
            ['name' => 'Travel & transport', 'description' => 'Fuel, taxis and delivery runs'],
            ['name' => 'Cleaning & maintenance', 'description' => 'Cleaning supplies and repairs'],
            ['name' => 'Packaging & bags', 'description' => 'Carrier bags, trays and wrap'],
            ['name' => 'Licences & government fees', 'description' => 'Municipality and permit charges'],
            ['name' => 'Marketing', 'description' => 'Leaflets, signage and offers'],
            ['name' => 'Bank & card charges', 'description' => 'Card machine and bank fees'],
            ['name' => 'Other', 'description' => 'Anything that fits nowhere else'],
        ];

        $sort = 0;

        foreach ($categories as $category) {
            ExpenseCategory::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [...$category, 'is_active' => true, 'sort_order' => $sort += 10],
            );
        }

        $manager = User::where('email', 'admin@fathimasupermarket.test')->first();

        if ($manager === null || Expense::exists()) {
            return;
        }

        $byName = ExpenseCategory::pluck('id', 'name');
        $grn = StockEntry::orderBy('id')->first();
        $supplier = Supplier::where('code', 'SUP-0001')->first();

        $rows = [
            ['category' => 'Rent', 'description' => 'Shop rent — monthly instalment', 'payee' => 'Al Faisal Properties', 'amount' => 8000, 'vat' => 0, 'method' => PaymentMethod::Transfer, 'days' => 3],
            ['category' => 'Electricity & water', 'description' => 'SEC electricity bill', 'payee' => 'Saudi Electricity Company', 'amount' => 1420.50, 'vat' => 15, 'method' => PaymentMethod::Transfer, 'days' => 5],
            ['category' => 'Salaries & wages', 'description' => 'Staff salaries — 4 employees', 'payee' => 'Store staff', 'amount' => 12000, 'vat' => 0, 'method' => PaymentMethod::Transfer, 'days' => 2],
            ['category' => 'Staff refreshments', 'description' => 'Tea, sugar and cups for the staff room', 'payee' => 'Al Nakheel Cafeteria', 'amount' => 86.96, 'vat' => 15, 'method' => PaymentMethod::Cash, 'days' => 1],
            ['category' => 'Travel & transport', 'description' => 'Fuel for the delivery van', 'payee' => 'Aldrees Petrol Station', 'amount' => 180, 'vat' => 15, 'method' => PaymentMethod::Card, 'days' => 4],
            ['category' => 'Travel & transport', 'description' => 'Taxi to the wholesale market', 'payee' => 'Careem', 'amount' => 45, 'vat' => 15, 'method' => PaymentMethod::Cash, 'days' => 6],
            ['category' => 'Cleaning & maintenance', 'description' => 'Chiller repair call-out', 'payee' => 'Cool Tech Services', 'amount' => 650, 'vat' => 15, 'method' => PaymentMethod::Cash, 'days' => 8],
            ['category' => 'Packaging & bags', 'description' => 'Carrier bags — 5,000 pieces', 'payee' => 'Riyadh Plastics', 'amount' => 420, 'vat' => 15, 'method' => PaymentMethod::Transfer, 'days' => 10, 'unpaid' => true],
            ['category' => 'Bank & card charges', 'description' => 'mada terminal monthly fee', 'payee' => 'Al Rajhi Bank', 'amount' => 120, 'vat' => 15, 'method' => PaymentMethod::Transfer, 'days' => 7],
            ['category' => 'Licences & government fees', 'description' => 'Municipality shop licence renewal', 'payee' => 'Riyadh Municipality', 'amount' => 1500, 'vat' => 0, 'method' => PaymentMethod::Transfer, 'days' => 12],
        ];

        foreach ($rows as $row) {
            $amount = round($row['amount'], 2);
            $vatAmount = round($amount * $row['vat'] / 100, 2);
            $date = now()->subDays($row['days'])->toDateString();
            $paid = ! ($row['unpaid'] ?? false);

            Expense::create([
                'reference_no' => $this->references->next(Expense::class, 'EXP'),
                'expense_date' => $date,
                'expense_category_id' => $byName[$row['category']],
                'payee' => $row['payee'],
                'description' => $row['description'],
                'amount' => $amount,
                'vat_rate' => $row['vat'],
                'vat_amount' => $vatAmount,
                'total' => round($amount + $vatAmount, 2),
                'payment_method' => $row['method'],
                'is_paid' => $paid,
                'paid_on' => $paid ? $date : null,
                'created_by' => $manager->id,
            ]);
        }

        // The first goods receipt, booked as the supplier bill it is.
        if ($grn !== null) {
            $net = round((float) $grn->grand_total / 1.15, 2);

            Expense::create([
                'reference_no' => $this->references->next(Expense::class, 'EXP'),
                'expense_date' => $grn->entry_date->toDateString(),
                'expense_category_id' => $byName['Goods purchase'],
                'supplier_id' => $supplier?->id ?? $grn->supplier_id,
                'stock_entry_id' => $grn->id,
                'invoice_number' => $grn->invoice_number,
                'description' => "Supplier bill for goods receipt {$grn->reference_no}",
                'amount' => $net,
                'vat_rate' => 15,
                'vat_amount' => round((float) $grn->grand_total - $net, 2),
                'total' => (float) $grn->grand_total,
                'payment_method' => PaymentMethod::Transfer,
                'is_paid' => false,
                'created_by' => $manager->id,
            ]);
        }
    }
}
