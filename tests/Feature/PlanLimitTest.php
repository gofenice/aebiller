<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Services\PlanLimits;
use App\Services\StoreProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What each plan lets a shop hold. A blank figure on the plan means no limit
 * at all — it must never be read as a cap of zero.
 */
class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, int|null>  $limits
     */
    protected function onPlanWith(array $limits): Plan
    {
        $plan = Plan::factory()->limited($limits)->create(['name' => 'Silver']);

        $this->store->update(['plan_id' => $plan->id]);
        $this->store->refresh();

        return $plan;
    }

    protected function limits(): PlanLimits
    {
        return app(PlanLimits::class);
    }

    public function test_a_blank_limit_means_unlimited(): void
    {
        $this->onPlanWith(['max_products' => null]);

        Product::factory()->count(3)->create();

        $this->assertTrue($this->limits()->allows('max_products'));
        $this->assertNull($this->limits()->reasonToBlock('max_products'));
    }

    public function test_a_store_with_no_plan_at_all_is_unlimited(): void
    {
        $this->store->update(['plan_id' => null]);
        $this->store->refresh();

        Product::factory()->count(3)->create();

        $this->assertTrue($this->limits()->allows('max_products'));
    }

    public function test_room_is_allowed_up_to_the_limit_and_refused_after_it(): void
    {
        $this->onPlanWith(['max_products' => 2]);

        Product::factory()->create();
        $this->assertTrue($this->limits()->allows('max_products'));

        Product::factory()->create();
        $this->assertFalse($this->limits()->allows('max_products'));
    }

    /**
     * A payload the product form would actually accept, so a refusal can only
     * have come from the plan limit and not from validation.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function productPayload(array $overrides = []): array
    {
        return [
            'name' => 'Aashirvaad Select Atta',
            'sku' => 'PKT-00099',
            'barcode' => '8901030765432',
            'type' => ProductType::Packaged->value,
            'category_id' => Category::factory()->create()->id,
            'unit_id' => Unit::factory()->create(['code' => 'pkt'])->id,
            'pack_size' => 5,
            'pack_unit_id' => Unit::factory()->fractional()->create(['code' => 'kg'])->id,
            'tax_rate' => 5,
            'cost_price' => 245,
            'selling_price' => 285,
            'reorder_level' => 10,
            'storage_type' => 'ambient',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_a_product_is_added_normally_while_there_is_room(): void
    {
        $this->onPlanWith(['max_products' => 2]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('products.store'), $this->productPayload())
            ->assertSessionHas('status');

        $this->assertSame(1, Product::query()->count());
    }

    public function test_adding_a_product_over_the_limit_is_blocked_and_says_which_plan_lifts_it(): void
    {
        $this->onPlanWith(['max_products' => 1]);
        Plan::factory()->limited(['max_products' => 5000])->create(['name' => 'Gold', 'sort_order' => 2]);

        Product::factory()->create();

        // A payload that would otherwise be accepted: only the limit stops it.
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('products.store'), $this->productPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'Silver')
                && str_contains($error, 'Gold'));

        $this->assertSame(1, Product::query()->count());
    }

    public function test_bills_are_counted_for_the_current_month_only(): void
    {
        $this->onPlanWith(['max_monthly_bills' => 2]);

        Sale::factory()->create(['sold_at' => now()->subMonthNoOverflow()]);
        Sale::factory()->create(['sold_at' => now()->subMonthNoOverflow()]);

        // Last month's bills are spent; this month starts again.
        $this->assertSame(0, $this->limits()->used('max_monthly_bills'));
        $this->assertTrue($this->limits()->allows('max_monthly_bills'));

        Sale::factory()->count(2)->create(['sold_at' => now()]);

        $this->assertSame(2, $this->limits()->used('max_monthly_bills'));
        $this->assertFalse($this->limits()->allows('max_monthly_bills'));
    }

    public function test_a_voided_bill_does_not_count_against_the_month(): void
    {
        $this->onPlanWith(['max_monthly_bills' => 2]);

        Sale::factory()->count(2)->create(['sold_at' => now()]);
        Sale::first()->update(['voided_at' => now()]);

        $this->assertSame(1, $this->limits()->used('max_monthly_bills'));
        $this->assertTrue($this->limits()->allows('max_monthly_bills'));
    }

    public function test_staff_accounts_and_members_are_counted_too(): void
    {
        $this->onPlanWith(['max_users' => 1, 'max_customers' => 1]);

        User::factory()->create();
        Customer::factory()->create();

        $this->assertFalse($this->limits()->allows('max_users'));
        $this->assertFalse($this->limits()->allows('max_customers'));
    }

    public function test_provisioning_a_shop_is_never_blocked_by_the_staff_limit(): void
    {
        // The owner is created while the shop is being set up. If the limit
        // caught that, signing up on the smallest plan would fail outright.
        $plan = Plan::factory()->limited(['max_users' => 1])->create();

        $store = Store::factory()->create(['slug' => 'newshop', 'plan_id' => $plan->id]);

        $result = app(StoreProvisioner::class)->provision($store, [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password1234',
        ]);

        $this->assertNotNull($result['user']);
    }

    public function test_the_summary_reports_what_is_used_against_each_limit(): void
    {
        $this->onPlanWith(['max_products' => 10, 'max_users' => null]);

        Product::factory()->count(2)->create();

        $summary = $this->limits()->summary();

        $this->assertSame(2, $summary['max_products']['used']);
        $this->assertSame(10, $summary['max_products']['limit']);
        $this->assertFalse($summary['max_products']['unlimited']);
        $this->assertTrue($summary['max_users']['unlimited']);
    }
}
