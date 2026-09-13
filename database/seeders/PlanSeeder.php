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
     *
     * Three tiers a shop can pick for itself, each payable monthly or yearly,
     * and one free plan that is only ever handed out from the platform.
     */
    public function run(): void
    {
        // Monthly prices in the platform's base currency; the rest are set by
        // hand so nothing depends on an exchange rate. The yearly price is
        // worked out from the discount, so there is no second price to keep up.
        $plans = [
            [
                'name' => 'Silver', 'slug' => 'silver', 'monthly_price' => 19,
                'yearly_discount_percent' => 20,
                'description' => 'For a single shop finding its feet.',
                'prices' => ['SAR' => 69, 'AED' => 69, 'INR' => 1499, 'GBP' => 15, 'EUR' => 18],
                'limits' => ['max_products' => 500, 'max_monthly_bills' => 1000, 'max_users' => 3, 'max_customers' => 1000],
            ],
            [
                'name' => 'Gold', 'slug' => 'gold', 'monthly_price' => 39,
                'yearly_discount_percent' => 20,
                'description' => 'For a busy shop with staff and a loyalty programme.',
                'prices' => ['SAR' => 145, 'AED' => 145, 'INR' => 2999, 'GBP' => 31, 'EUR' => 36],
                'limits' => ['max_products' => 5000, 'max_monthly_bills' => 10000, 'max_users' => 10, 'max_customers' => 10000],
            ],
            [
                'name' => 'Platinum', 'slug' => 'platinum', 'monthly_price' => 79,
                'yearly_discount_percent' => 20,
                'description' => 'Everything unlimited, with priority support.',
                'prices' => ['SAR' => 295, 'AED' => 290, 'INR' => 5999, 'GBP' => 63, 'EUR' => 73],
                'limits' => ['max_products' => null, 'max_monthly_bills' => null, 'max_users' => null, 'max_customers' => null],
            ],
        ];

        foreach ($plans as $index => $plan) {
            $currencies = $plan['prices'];
            $limits = $plan['limits'];
            unset($plan['prices'], $plan['limits']);

            $record = Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    ...$plan,
                    ...$limits,
                    'billing_period' => BillingPeriod::Monthly,
                    'currency_code' => config('tenancy.base_currency'),
                    'is_active' => true,
                    'is_public' => true,
                    'is_free' => false,
                    'sort_order' => $index,
                ],
            );

            foreach ($currencies as $code => $amount) {
                PlanPrice::updateOrCreate(
                    ['plan_id' => $record->id, 'currency_code' => $code],
                    ['amount' => $amount],
                );
            }
        }

        // Never shown on the pricing table: put a shop on this from the Stores
        // screen and it is never invoiced again.
        Plan::updateOrCreate(
            ['slug' => 'lifetime-free'],
            [
                'name' => 'Lifetime Free',
                'monthly_price' => 0,
                'yearly_discount_percent' => 0,
                'billing_period' => BillingPeriod::Lifetime,
                'currency_code' => config('tenancy.base_currency'),
                'description' => 'Given by hand. Never invoiced, nothing capped.',
                'is_active' => true,
                'is_public' => false,
                'is_free' => true,
                'sort_order' => 99,
                'max_products' => null,
                'max_monthly_bills' => null,
                'max_users' => null,
                'max_customers' => null,
            ],
        );

        $this->retireReplacedPlans();
    }

    /**
     * The plans the three tiers replaced. One no shop is on is deleted; one
     * still in use is only hidden, because deleting it would take its stores'
     * price history with it.
     */
    protected function retireReplacedPlans(): void
    {
        $replaced = ['starter', 'standard', 'standard-yearly', 'premium', 'lifetime'];

        foreach (Plan::whereIn('slug', $replaced)->withCount('stores')->get() as $plan) {
            if ($plan->stores_count === 0) {
                $plan->delete();

                continue;
            }

            $plan->update(['is_active' => false, 'is_public' => false]);
        }
    }
}
