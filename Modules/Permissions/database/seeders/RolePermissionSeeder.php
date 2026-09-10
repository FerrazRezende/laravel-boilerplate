<?php

declare(strict_types=1);

namespace Modules\Permissions\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Add your application-specific permissions to the $permissions array below.
     * The boilerplate creates default "Full Access" and "Read Only" roles.
     */
    public function run(): void
    {
        // Define all permissions for the application
        // Add your own permissions here as you build your application
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web']
            );
        }

        // Create default "Full Access" role with all permissions
        $fullAccessRole = Role::firstOrCreate(
            ['name' => 'Full Access'],
            ['guard_name' => 'web']
        );

        // Assign all permissions to the role
        $fullAccessRole->syncPermissions(Permission::all());

        $this->command->info('Created '.count($permissions).' permissions.');
        $this->command->info('Created "Full Access" role with all permissions.');

        // Create "Read Only" role with view permissions only
        $readOnlyRole = Role::firstOrCreate(
            ['name' => 'Read Only'],
            ['guard_name' => 'web']
        );

        // Get all view permissions
        $viewPermissions = Permission::where('name', 'like', '%.view')->get();
        $readOnlyRole->syncPermissions($viewPermissions);

        $this->command->info('Created "Read Only" role with view permissions.');
        $this->command->info('Assigning "Read Only" role to regular users...');

        // Assign "Read Only" role to non-admin users
        $nonAdminUsers = User::where('is_admin', false)->get();
        foreach ($nonAdminUsers as $user) {
            $user->assignRole('Read Only');
        }

        $this->command->info('Assigned "Read Only" role to '.$nonAdminUsers->count().' users.');
    }
}
