<?php

declare(strict_types=1);

namespace Modules\Permissions\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Identity\Models\User;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_every_system_route_is_behind_the_admin_middleware(): void
    {
        // The guard lives on the route groups now, so a route that slips out
        // of them would be open to any signed-in user.
        $open = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'system/'))
            ->reject(fn ($route) => in_array('admin', $route->gatherMiddleware(), true))
            ->map(fn ($route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame(['system/stop-impersonating'], $open);
    }

    public function test_non_admins_cannot_write_to_the_system_area(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->put("/system/users/{$target->id}", [
            'name' => 'Changed',
            'email' => $target->email,
        ])->assertForbidden();

        $this->actingAs($user)->post("/system/users/{$target->id}/impersonate")->assertForbidden();

        $this->assertNotSame('Changed', $target->fresh()->name);
    }

    public function test_the_last_active_admin_cannot_be_deactivated(): void
    {
        $admin = $this->admin();

        // An inactive admin cannot sign in, so it does not count as a spare.
        User::factory()->inactive()->create(['is_admin' => true]);

        $this->actingAs($admin)->deleteJson("/system/users/{$admin->id}")->assertUnprocessable();
        $this->actingAs($admin)->putJson("/system/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'active' => false,
        ])->assertUnprocessable();

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_an_admin_can_be_deactivated_while_another_remains(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->actingAs($admin)->deleteJson("/system/users/{$other->id}")->assertOk();

        $this->assertFalse($other->fresh()->isActive());
    }

    public function test_user_search_ignores_case(): void
    {
        User::factory()->create(['name' => 'Maria Souza']);

        $this->actingAs($this->admin())
            ->getJson('/system/users?search=maria')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Maria Souza']);
    }

    public function test_activity_page_size_is_capped(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson("/system/users/{$admin->id}/activities?per_page=100000")
            ->assertOk()
            ->assertJsonPath('per_page', 100);
    }

    public function test_impersonation_round_trip(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post("/system/users/{$user->id}/impersonate")->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // The impersonated user is not an admin, and is still the one who
        // has to be able to stop.
        $this->post('/system/stop-impersonating')->assertRedirect('/system/users');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_deactivated_user_cannot_be_impersonated(): void
    {
        $admin = $this->admin();
        $user = User::factory()->inactive()->create();

        $this->actingAs($admin)->post("/system/users/{$user->id}/impersonate")->assertSessionHasErrors('error');

        $this->assertAuthenticatedAs($admin);
    }
}
