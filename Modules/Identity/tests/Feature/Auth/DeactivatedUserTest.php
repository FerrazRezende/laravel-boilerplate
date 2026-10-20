<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Tests\TestCase;

class DeactivatedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_user_cannot_sign_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_user_gets_the_same_error_as_a_wrong_password(): void
    {
        // A distinct message would confirm the address has an account.
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    public function test_a_session_opened_before_deactivation_is_ended(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['active' => false])->save();

        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_deactivated_user_gets_401_on_json_requests(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_a_newly_created_user_is_active_without_a_reload(): void
    {
        $this->assertTrue(User::factory()->create()->isActive());
        $this->assertTrue((new User)->isActive());
    }
}
