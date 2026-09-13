<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a ready-to-use store: staff accounts, master data, a starter
     * catalogue and enough stock movement to make every screen meaningful.
     */
    public function run(): void
    {
        $this->call([
            // The platform's own sign-in and price list, on the central domain.
            PlatformUserSeeder::class,
            PlanSeeder::class,
            // Then the store everything else belongs to, which also puts that
            // store into context for the seeders that follow.
            StoreSeeder::class,
            UserSeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            InventorySeeder::class,
            ExpenseSeeder::class,
            LoyaltySeeder::class,
        ]);
    }
}
