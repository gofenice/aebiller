<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_super_admin_can_reach_staff_accounts(): void
    {
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())->get('/users')->assertOk();
    }

    public function test_an_admin_cannot_delete_a_product(): void
    {
        $product = Product::factory()->create(['current_stock' => 0]);

        $this->actingAs(User::factory()->create())
            ->delete(route('products.destroy', $product))
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_a_super_admin_can_delete_a_product_without_stock(): void
    {
        $product = Product::factory()->create(['current_stock' => 0]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted($product);
    }

    public function test_a_product_holding_stock_cannot_be_deleted(): void
    {
        $product = Product::factory()->create(['current_stock' => 12]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('products.destroy', $product))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($product);
    }

    public function test_a_super_admin_cannot_delete_their_own_account(): void
    {
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)
            ->delete(route('users.destroy', $owner))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_a_super_admin_cannot_strip_their_own_super_admin_role(): void
    {
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)
            ->put(route('users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'admin',
                'is_active' => '1',
            ])
            ->assertSessionHas('error');

        $this->assertTrue($owner->fresh()->isSuperAdmin());
    }
}
