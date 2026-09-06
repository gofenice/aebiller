<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_an_active_staff_member_can_sign_in(): void
    {
        $user = User::factory()->create(['email' => 'manager@store.test']);

        $this->post('/login', [
            'email' => 'manager@store.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_a_disabled_account_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['email' => 'former@store.test']);

        $this->post('/login', [
            'email' => 'former@store.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'manager@store.test']);

        $this->post('/login', [
            'email' => 'manager@store.test',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_signed_in_user_can_sign_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
