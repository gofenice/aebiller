<?php

namespace Database\Seeders;

use App\Enums\CardTheme;
use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Database\Seeder;

class LoyaltySeeder extends Seeder
{
    public function __construct(protected LoyaltyService $loyalty) {}

    /**
     * The programme rules, four tiers with their card designs, and a handful
     * of members so the screens have something to show.
     */
    public function run(): void
    {
        LoyaltySetting::current();

        $tiers = [
            ['name' => 'Classic', 'min_spend' => 0, 'earn_multiplier' => 1, 'card_theme' => CardTheme::Emerald, 'perks' => '1 point for every riyal spent'],
            ['name' => 'Silver', 'min_spend' => 3000, 'earn_multiplier' => 1.25, 'card_theme' => CardTheme::Silver, 'perks' => '25% more points on every bill'],
            ['name' => 'Gold', 'min_spend' => 8000, 'earn_multiplier' => 1.5, 'card_theme' => CardTheme::Gold, 'perks' => '50% more points and first look at offers'],
            ['name' => 'Platinum', 'min_spend' => 20000, 'earn_multiplier' => 2, 'card_theme' => CardTheme::Platinum, 'perks' => 'Double points on every bill'],
        ];

        foreach ($tiers as $index => $tier) {
            LoyaltyTier::updateOrCreate(['name' => $tier['name']], [...$tier, 'sort_order' => $index]);
        }

        $manager = User::where('email', 'admin@fathimasupermarket.test')->first() ?? User::first();

        if ($manager === null || Customer::exists()) {
            return;
        }

        $members = [
            ['name' => 'Aisha Rahman', 'phone' => '0551234567', 'birth_date' => '1988-03-14', 'city' => 'Riyadh', 'marketing_opt_in' => true],
            ['name' => 'Mohammed Al-Harbi', 'phone' => '0502345678', 'birth_date' => '1979-11-02', 'city' => 'Riyadh', 'marketing_opt_in' => true],
            ['name' => 'Fatima Noor', 'phone' => '0563456789', 'birth_date' => '1995-07-21', 'city' => 'Riyadh', 'marketing_opt_in' => false],
            ['name' => 'Abdullah Qahtani', 'phone' => '0534567890', 'birth_date' => null, 'city' => 'Riyadh', 'marketing_opt_in' => true],
            ['name' => 'Priya Menon', 'phone' => '0545678901', 'birth_date' => '1990-01-09', 'city' => 'Riyadh', 'marketing_opt_in' => true],
            ['name' => 'Yusuf Ibrahim', 'phone' => '0556789012', 'birth_date' => '1985-05-30', 'city' => 'Riyadh', 'marketing_opt_in' => false],
            ['name' => 'Noura Al-Otaibi', 'phone' => '0597890123', 'birth_date' => '2000-12-18', 'city' => 'Riyadh', 'marketing_opt_in' => true],
            ['name' => 'Shiyas Nazar', 'phone' => '0508901234', 'birth_date' => null, 'city' => 'Riyadh', 'marketing_opt_in' => true],
        ];

        foreach ($members as $index => $member) {
            $customer = $this->loyalty->enrol($member, $manager);

            // A few long-standing shoppers bring their old stamp-card points over.
            if ($index % 3 === 0) {
                $this->loyalty->adjust($customer, 150 + ($index * 40), 'Carried over from the paper stamp card', $manager);
            }
        }
    }
}
