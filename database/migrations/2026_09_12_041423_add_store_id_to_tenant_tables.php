<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every table that holds one store's data.
     *
     * @var array<int, string>
     */
    protected const TENANT_TABLES = [
        'users', 'categories', 'brands', 'units', 'suppliers', 'products',
        'stock_batches', 'stock_entries', 'stock_entry_items', 'stock_adjustments',
        'stock_adjustment_items', 'stock_movements', 'sales', 'sale_items',
        'expense_categories', 'expenses', 'customers', 'loyalty_tiers',
        'loyalty_cards', 'loyalty_transactions', 'loyalty_settings',
    ];

    /**
     * Codes and numbers that only have to be unique inside one store: two
     * shops may both have a product SKU PKT-00001 or an invoice INV-2609-0001.
     *
     * @var array<string, array<int, string>>
     */
    protected const SCOPED_UNIQUES = [
        'users' => ['email'],
        'categories' => ['slug'],
        'brands' => ['slug'],
        'units' => ['code'],
        'suppliers' => ['code'],
        'products' => ['sku', 'barcode'],
        'stock_entries' => ['reference_no'],
        'stock_adjustments' => ['reference_no'],
        'sales' => ['invoice_no'],
        'expense_categories' => ['slug'],
        'expenses' => ['reference_no'],
        'customers' => ['phone'],
        'loyalty_cards' => ['number'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TENANT_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('store_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    // A store that is deleted takes its own data with it.
                    ->cascadeOnDelete();
            });
        }

        // Anything already in the database belongs to the store that was here
        // before there were stores.
        $storeId = $this->existingStoreId();

        if ($storeId !== null) {
            foreach (self::TENANT_TABLES as $table) {
                DB::table($table)->whereNull('store_id')->update(['store_id' => $storeId]);
            }
        }

        foreach (self::SCOPED_UNIQUES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropUnique([$column]);
                    $blueprint->unique(['store_id', $column]);
                }
            });
        }
    }

    /**
     * The store the rows already in the database belong to, creating it from
     * the current settings on an installation that predates multi-store.
     */
    protected function existingStoreId(): ?int
    {
        $existing = DB::table('stores')->orderBy('id')->value('id');

        if ($existing !== null) {
            return $existing;
        }

        if (! DB::table('users')->exists() && ! DB::table('products')->exists()) {
            return null;
        }

        return DB::table('stores')->insertGetId([
            'name' => config('app.name'),
            'slug' => env('APP_STORE_SLUG', 'fathima'),
            'status' => 'active',
            'currency_code' => config('inventory.currency_code', 'SAR'),
            'currency_symbol' => config('inventory.currency_symbol', 'SAR '),
            'timezone' => config('app.timezone', 'Asia/Riyadh'),
            'vat_number' => config('inventory.store_vat_number'),
            'address' => config('inventory.store_address'),
            'phone' => config('inventory.store_phone'),
            'expiry_alert_days' => config('inventory.expiry_alert_days', 30),
            'tax_rates' => json_encode(config('inventory.tax_rates', [0, 15])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::SCOPED_UNIQUES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropUnique(['store_id', $column]);
                    $blueprint->unique([$column]);
                }
            });
        }

        foreach (self::TENANT_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('store_id');
            });
        }
    }
};
