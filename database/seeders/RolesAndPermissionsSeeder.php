<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions before doing anything
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = Config::get('panel.guard', 'web');

        // -------------------------------------------------------------------------
        // Step 1: Build the full desired permission list from config
        // -------------------------------------------------------------------------
        $desiredPermissions = $this->buildPermissionList();

        // -------------------------------------------------------------------------
        // Step 2: Create any missing permissions (never truncate, safe for production)
        // -------------------------------------------------------------------------
        foreach ($desiredPermissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        }

        // -------------------------------------------------------------------------
        // Step 3: Remove permissions that are no longer in config
        //         but never remove protected permissions
        // -------------------------------------------------------------------------
        $protectedPermissions = Config::get('panel.protected_permissions', []);

        Permission::query()
            ->where('guard_name', $guard)
            ->whereNotIn('name', $desiredPermissions)
            ->whereNotIn('name', $protectedPermissions)
            ->each(function (Permission $permission) {
                // Detach from roles first, then delete
                $permission->roles()->detach();
                $permission->delete();
            });

        // -------------------------------------------------------------------------
        // Step 4: Create protected/system roles
        // -------------------------------------------------------------------------
        $protectedRoles = Config::get('panel.protected_roles', []);

        foreach ($protectedRoles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
            ]);
        }

        // -------------------------------------------------------------------------
        // Step 5: Assign all permissions to super-admin
        //         (Gate::before bypass means this is only for explicit checks,
        //          but good to have for getAllPermissions() calls)
        // -------------------------------------------------------------------------
        $superAdminRole = Role::where('name', Config::get('panel.super_admin_role'))
            ->where('guard_name', $guard)
            ->first();

        if ($superAdminRole) {
            $superAdminRole->syncPermissions(Permission::where('guard_name', $guard)->get());
        }

        // -------------------------------------------------------------------------
        // Step 6: Assign default permissions to admin role
        //         Excluded permissions are defined in config
        // -------------------------------------------------------------------------
        $adminRole = Role::where('name', 'admin')
            ->where('guard_name', $guard)
            ->first();

        if ($adminRole) {
            $excluded = Config::get('panel.admin_excluded_permissions', []);

            $adminPermissions = Permission::where('guard_name', $guard)
                ->whereNotIn('name', $excluded)
                ->get();

            $adminRole->syncPermissions($adminPermissions);
        }

        // -------------------------------------------------------------------------
        // Step 7: user role gets no permissions (app users, no panel access)
        //         Only sync if it already has permissions that shouldn't be there
        // -------------------------------------------------------------------------
        $userRole = Role::where('name', Config::get('panel.app_user_role'))
            ->where('guard_name', $guard)
            ->first();

        if ($userRole && $userRole->permissions()->exists()) {
            $userRole->syncPermissions([]);
        }

        // -------------------------------------------------------------------------
        // Step 8: Clear cache again after all changes
        // -------------------------------------------------------------------------
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Roles and permissions seeded successfully.');
        $this->command->info('Permissions: '.Permission::where('guard_name', $guard)->count());
        $this->command->info('Roles: '.Role::where('guard_name', $guard)->count());
    }

    // -------------------------------------------------------------------------
    // Builds the flat list of all permission names from config/panel.php
    // Format: {module}.{action}  e.g. users.view, vpn_servers.create
    // -------------------------------------------------------------------------
    protected function buildPermissionList(): array
    {
        $permissions = [];

        $modules = Config::get('panel.modules', []);

        foreach ($modules as $module => $config) {
            // Support both formats:
            //   'module' => ['view', 'create']          (simple array)
            //   'module' => ['label' => ..., 'actions' => [...]]  (rich config)
            $actions = isset($config['actions']) ? $config['actions'] : $config;

            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return array_unique($permissions);
    }
}
