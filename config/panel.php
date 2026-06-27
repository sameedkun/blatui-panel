<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guard Name
    |--------------------------------------------------------------------------
    |
    | The guard used for all roles and permissions in this panel.
    | Keep this consistent across all Role/Permission creation calls.
    |
    */
    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Super Admin Role
    |--------------------------------------------------------------------------
    |
    | This role bypasses all permission checks via Gate::before().
    | It cannot be deleted, renamed, or edited from the UI.
    |
    */
    'super_admin_role' => 'super-admin',

    /*
    |--------------------------------------------------------------------------
    | App User Role
    |--------------------------------------------------------------------------
    |
    | Assigned to every user that registers via the mobile/web app.
    | Has no panel access. Used as a marker to distinguish app users
    | from staff/admin accounts in the same users table.
    |
    */
    'app_user_role' => 'user',

    /*
    |--------------------------------------------------------------------------
    | Protected Roles
    |--------------------------------------------------------------------------
    |
    | These roles cannot be deleted or renamed from the panel UI.
    | Always include super_admin_role and app_user_role here.
    |
    */
    'protected_roles' => [
        'super-admin',
        'admin',
        'user',
        'guest',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Permissions
    |--------------------------------------------------------------------------
    |
    | These permissions cannot be deleted from the panel UI.
    | Panel access permissions must always be protected.
    |
    */
    'protected_permissions' => [
        'panel.access-admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel Access Map
    |--------------------------------------------------------------------------
    |
    | Maps panel identifiers to their gating permission.
    | Used in login redirect logic and route middleware.
    |
    | Usage:
    |   $permission = config('panel.access')['admin'];
    |   $user->can($permission); // true/false
    |
    */
    'access' => [
        'admin' => 'panel.access-admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Standard Action Vocabulary
    |--------------------------------------------------------------------------
    |
    | These are the only allowed action keywords across all modules.
    | Do not invent new actions without adding them here first.
    |
    | CRUD:        view, create, edit, delete
    | Soft delete: restore, force-delete
    | User states: ban, unban
    | Bulk ops:    export, import
    | Operational: manage, access, reply, assign
    |
    */
    'action_vocabulary' => [
        'view',
        'create',
        'edit',
        'delete',
        'restore',
        'force-delete',
        'ban',
        'unban',
        'export',
        'import',
        'manage',
        'access',
        'reply',
        'assign',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules & Permissions
    |--------------------------------------------------------------------------
    |
    | Defines all application modules and the actions available within each.
    | Permissions are auto-generated as: {module}.{action}
    |
    | Module groups are used to organise the permission matrix UI:
    |   - system
    |   - infrastructure
    |   - app
    |   - users
    |   - support
    |   - content
    |   - settings
    |
    | Each module entry:
    |   'label'      => Human-readable name shown in the UI
    |   'group'      => Category group for the permission matrix and sidebar
    |   'actions'    => List of allowed actions from the vocabulary above
    |   'route'      => (optional) Route name; defaults to admin.{kebab-key}.index
    |   'icon'       => (optional) FontAwesome icon name for sidebar
    |   'show_in_nav'=> (optional) Hide from sidebar even if module exists. Default true.
    |
    */
    'modules' => [

        // --- System ---
        'dashboard' => [
            'label' => 'Dashboard',
            'group' => 'system',
            'actions' => ['view'],
        ],

        // --- Users ---
        'users' => [
            'label' => 'Users',
            'group' => 'users',
            'actions' => ['view', 'create', 'edit', 'delete', 'restore', 'force-delete', 'ban', 'unban', 'export', 'manage'],
            'icon' => 'users',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Module Groups
    |--------------------------------------------------------------------------
    |
    | Display labels for each group. Used to render group headings
    | in the permission matrix UI on the role create/edit page.
    |
    */
    'groups' => [
        'system' => 'System',
        'infrastructure' => 'Infrastructure',
        'users' => 'Users',
        'billing' => 'Plans & Billing',
        'app' => 'App Management',
        'support' => 'Support',
        'content' => 'Content',
        'settings' => 'Settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Admin Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions assigned to the 'admin' role on first seed.
    | The admin role is seeded with everything except a few destructive
    | system-level actions that only super-admin should touch.
    |
    | Set to ['*'] to grant all permissions, or list specific ones to exclude.
    |
    */
    'admin_excluded_permissions' => [
        'users.force-delete',
        'roles.delete',
        'logs.access',
        'activity_logs.export',
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial Admin User
    |--------------------------------------------------------------------------
    | When seeding the database, this user is created and assigned the super admin role.
    | Update the .env file with your desired credentials before running db:seed.
    |
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Super Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
