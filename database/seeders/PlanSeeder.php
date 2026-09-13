<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * What the platform charges. Prices are a starting point — change them on
     * the Plans screen.
     */
    public function run(): void
    {
        // Base prices are in the platform's base currency; the rest are set by
        // hand so nothing depends on an exchange rate.
        $plans = [
            ['name' => 'Starter', 'slug' => 'starter', 'monthly_price' => 19, 'billing_period' => BillingPeriod::Monthly, 'description' => 'One till, one shop — inventory and billing.',
                'prices' => ['SAR' => 69, 'AED' => 69, 'INR' => 1499, 'GBP' => 15, 'EUR' => 18]],
            ['name' => 'Standard', 'slug' => 'standard', 'monthly_price' => 39, 'billing_period' => BillingPeriod::Monthly, 'description' => 'Everything in Starter plus the loyalty programme and reports.',
                'prices' => ['SAR' => 145, 'AED' => 145, 'INR' => 2999, 'GBP' => 31, 'EUR' => 36]],
            ['name' => 'Standard yearly', 'slug' => 'standard-yearly', 'monthly_price' => 390, 'billing_period' => BillingPeriod::Yearly, 'description' => 'The Standard plan paid yearly — two months free.',
                'prices' => ['SAR' => 1450, 'AED' => 1450, 'INR' => 29990, 'GBP' => 310, 'EUR' => 360]],
            ['name' => 'Premium', 'slug' => 'premium', 'monthly_price' => 69, 'billing_period' => BillingPeriod::Monthly, 'description' => 'Everything in Standard, with priority support.',
                'prices' => ['SAR' => 259, 'AED' => 259, 'INR' => 5499, 'GBP' => 55, 'EUR' => 64]],
            ['name' => 'Lifetime', 'slug' => 'lifetime', 'monthly_price' => 899, 'billing_period' => BillingPeriod::Lifetime, 'description' => 'Paid once, never invoiced again.',
                'prices' => ['SAR' => 3370, 'AED' => 3300, 'INR' => 74900, 'GBP' => 720, 'EUR' => 830]],
        ];

        foreach ($plans as $index => $plan) {
            $currencies = $plan['prices'];
            unset($plan['prices']);

            $record = Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                [...$plan, 'currency_code' => config('tenancy.base_currency'), 'is_active' => true, 'sort_order' => $index],
            );

            foreach ($currencies as $code => $amount) {
                PlanPrice::updateOrCreate(
                    ['plan_id' => $record->id, 'currency_code' => $code],
                    ['amount' => $amount],
                );
            }
        }
    }
}
