<?php

declare(strict_types=1);

namespace Modules\Permissions\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private function grant(User $user, string ...$permissions): Role
    {
        $role = Role::firstOrCreate(['name' => 'Tester', 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $role;
    }

    public function test_a_permission_granted_through_a_role_is_held(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->grant($user, 'posts.view', 'posts.edit');
        Permission::firstOrCreate(['name' => 'posts.delete', 'guard_name' => 'web']);

        $this->assertTrue($user->checkPermission('posts.view'));
        $this->assertTrue($user->checkPermission('posts.edit'));
        $this->assertFalse($user->checkPermission('posts.delete'));
    }

    public function test_checking_an_unseeded_permission_throws_rather_than_denying(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->grant($user, 'posts.view');

        // Worth pinning: a policy that checks a permission nobody seeded raises
        // PermissionDoesNotExist, so the user gets a 500 instead of a 403. Every
        // permission a policy names must exist in RolePermissionSeeder.
        $this->expectException(PermissionDoesNotExist::class);

        $user->checkPermission('posts.publish');
    }

    public function test_a_denied_permission_beats_the_role_that_grants_it(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->grant($user, 'posts.view', 'posts.edit');

        $denied = Permission::where('name', 'posts.edit')->firstOrFail();
        $user->update(['denied_permissions' => [$denied->id]]);

        // This is the whole reason policies must call checkPermission: Spatie's
        // own answer still says yes, and following it would hand back access an
        // admin believed they had revoked.
        $this->assertTrue($user->fresh()->hasPermissionTo('posts.edit'));
        $this->assertFalse($user->fresh()->checkPermission('posts.edit'));
        $this->assertTrue($user->fresh()->checkPermission('posts.view'));
    }

    public function test_denied_permissions_are_excluded_from_the_effective_set(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->grant($user, 'posts.view', 'posts.edit');

        $denied = Permission::where('name', 'posts.edit')->firstOrFail();
        $user->update(['denied_permissions' => [$denied->id]]);

        $names = $user->fresh()->getActualPermissions()->pluck('name')->all();

        $this->assertContains('posts.view', $names);
        $this->assertNotContains('posts.edit', $names);
    }

    public function test_the_frontend_never_receives_a_denied_permission(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->grant($user, 'posts.view', 'posts.edit');

        $denied = Permission::where('name', 'posts.edit')->firstOrFail();
        $user->update(['denied_permissions' => [$denied->id]]);

        // If a denied permission leaked into this prop the UI would offer an
        // action the server then refuses.
        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('userPermissions', ['posts.view']));
    }

    public function test_system_area_is_closed_to_non_admins(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->grant($user, 'users.view', 'users.edit', 'users.delete');

        // Holding every users.* permission is still not enough: /system is
        // gated on is_admin, not on RBAC.
        foreach (['/system/users', '/system/features', '/system/permissions'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_system_area_is_open_to_admins(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['/system/users', '/system/features', '/system/permissions'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
