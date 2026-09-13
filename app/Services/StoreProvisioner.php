<?php

namespace App\Services;

use App\Enums\CardTheme;
use App\Enums\UserRole;
use App\Models\ExpenseCategory;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sets a brand-new store up so its owner can sign in and start working:
 * an owner account, the units goods are measured in, the categories money is
 * spent under, and a loyalty programme ready to switch on.
 */
class StoreProvisioner
{
    /**
     * @param  array{name: string, email: string, password: string|null}  $owner
     * @return array{user: User, password: string}
     */
    public function provision(Store $store, array $owner): array
    {
        // A password the platform can read once and hand over; the owner
        // changes it from their own staff screen afterwards.
        $password = $owner['password'] ?: Str::password(12, symbols: false);

        $user = StoreContext::runFor($store, function () use ($owner, $password): User {
            return DB::transaction(function () use ($owner, $password): User {
                $user = User::create([
                    'name' => $owner['name'],
                    'email' => $owner['email'],
                    'password' => $password,
                    'role' => UserRole::SuperAdmin,
                    'is_active' => true,
                ]);

                $this->seedUnits();
                $this->seedExpenseCategories();
                $this->seedLoyaltyProgramme();

                return $user;
            });
        });

        return ['user' => $user, 'password' => $password];
    }

    protected function seedUnits(): void
    {
        $units = [
            ['name' => 'Piece', 'code' => 'pcs', 'allows_decimal' => false],
            ['name' => 'Packet', 'code' => 'pkt', 'allows_decimal' => false],
            ['name' => 'Box', 'code' => 'box', 'allows_decimal' => false],
            ['name' => 'Kilogram', 'code' => 'kg', 'allows_decimal' => true],
            ['name' => 'Gram', 'code' => 'g', 'allows_decimal' => true],
            ['name' => 'Litre', 'code' => 'ltr', 'allows_decimal' => true],
            ['name' => 'Millilitre', 'code' => 'ml', 'allows_decimal' => true],
            ['name' => 'Dozen', 'code' => 'dzn', 'allows_decimal' => false],
        ];

        foreach ($units as $unit) {
            Unit::create([...$unit, 'is_active' => true]);
        }
    }

    protected function seedExpenseCategories(): void
    {
        $categories = [
            'Goods purchase', 'Rent', 'Electricity & water', 'Salaries & wages',
            'Travel & transport', 'Cleaning & maintenance', 'Packaging & bags',
            'Licences & government fees', 'Marketing', 'Bank & card charges', 'Other',
        ];

        foreach ($categories as $index => $name) {
            ExpenseCategory::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    /**
     * The loyalty ladder every store starts with. It earns nothing until the
     * owner switches the programme on.
     */
    protected function seedLoyaltyProgramme(): void
    {
        $tiers = [
            ['name' => 'Classic', 'min_spend' => 0, 'earn_multiplier' => 1, 'card_theme' => CardTheme::Emerald, 'perks' => '1 point for every 1 spent'],
            ['name' => 'Silver', 'min_spend' => 3000, 'earn_multiplier' => 1.25, 'card_theme' => CardTheme::Silver, 'perks' => '25% more points on every bill'],
            ['name' => 'Gold', 'min_spend' => 8000, 'earn_multiplier' => 1.5, 'card_theme' => CardTheme::Gold, 'perks' => '50% more points and first look at offers'],
            ['name' => 'Platinum', 'min_spend' => 20000, 'earn_multiplier' => 2, 'card_theme' => CardTheme::Platinum, 'perks' => 'Double points on every bill'],
        ];

        foreach ($tiers as $index => $tier) {
            LoyaltyTier::create([...$tier, 'sort_order' => $index]);
        }

        LoyaltySetting::create(['is_enabled' => false]);
    }
}
