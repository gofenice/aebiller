<?php

namespace Database\Seeders;

use App\Enums\StoreStatus;
use App\Models\Plan;
use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * The first store, and the one every seeder after this writes into.
     */
    public function run(): void
    {
        $defaults = config('tenancy.defaults');

        $plan = Plan::where('slug', 'standard')->first();

        $store = Store::updateOrCreate(
            ['slug' => env('APP_STORE_SLUG', 'fathima')],
            [
                'name' => config('app.name'),
                'status' => StoreStatus::Active,
                'plan_id' => $plan?->id,
                'billing_day' => 1,
                'grace_days' => 7,
                'auto_suspend' => true,
                'billing_starts_on' => now()->startOfMonth(),
                'next_invoice_on' => now()->startOfMonth()->addMonthNoOverflow(),
                'owner_name' => 'Store Owner',
                'currency_code' => config('inventory.currency_code', $defaults['currency_code']),
                'currency_symbol' => config('inventory.currency_symbol', $defaults['currency_symbol']),
                'timezone' => $defaults['timezone'],
                'vat_number' => config('inventory.store_vat_number'),
                'address' => config('inventory.store_address'),
                'phone' => config('inventory.store_phone'),
                'expiry_alert_days' => config('inventory.expiry_alert_days', $defaults['expiry_alert_days']),
                'tax_rates' => config('inventory.tax_rates', $defaults['tax_rates']),
            ],
        );

        // Everything seeded from here on belongs to this store.
        StoreContext::set($store);

        $this->command?->info("Seeding into {$store->name} ({$store->host()}).");
    }
}
