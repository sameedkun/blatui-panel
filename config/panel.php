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
    */
    'super_admin_role' => 'super-admin',

    /*
    |--------------------------------------------------------------------------
    | Protected Roles
    |--------------------------------------------------------------------------
    |
    | These roles cannot be deleted or renamed from the panel UI.
    | Only real RBAC (staff) roles belong here. App users and guests are
    | distinguished by the users.type column, not by a role.
    |
    */
    'protected_roles' => [
        'super-admin',
        'admin',
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
    | Operational: manage, access, reply, assign, convert, merge
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
        'convert',
        'merge',
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
    |   - administration
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

        // --- Dashboard ---
        'dashboard' => [
            'label' => 'Dashboard',
            'actions' => ['view'],
        ],

        // --- Management ---
        'users' => [
            'label' => 'Users',
            'group' => 'management',
            'actions' => ['view', 'create', 'edit', 'delete', 'restore', 'force-delete', 'ban', 'unban', 'export', 'manage'],
            'icon' => 'users',
        ],
        'guests' => [
            'label' => 'Guests',
            'group' => 'management',
            'actions' => ['view', 'delete', 'restore', 'force-delete', 'ban', 'unban', 'export', 'manage', 'convert', 'merge'],
            'icon' => 'user-friends',
        ],

        // --- Administration ---
        'staff' => [
            'label' => 'Staff',
            'group' => 'administration',
            'actions' => ['view', 'create', 'edit', 'delete', 'restore', 'force-delete', 'ban', 'unban', 'export', 'manage'],
            'icon' => 'shield-user',
        ],
        'roles' => [
            'label' => 'Roles',
            'group' => 'administration',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'icon' => 'key',
        ],
        'activity_logs' => [
            'label' => 'Activity Logs',
            'group' => 'administration',
            'actions' => ['view', 'export'],
            'icon' => 'clipboard-list',
        ],

        'settings' => [
            'label' => 'Settings',
            'group' => 'settings',
            'actions' => [
                'view',
            ],
            'children' => [
                'general' => ['view', 'edit'],
                'mail' => ['view', 'edit'],
                'policies' => ['view', 'edit'],
            ],
            'icon' => 'gear',
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
        'administration' => 'Administration',
        'management' => 'Management',
        'infrastructure' => 'Infrastructure',
        'app' => 'Application',
        'support' => 'Support',
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
    | Account Deletion Grace Period
    |--------------------------------------------------------------------------
    |
    | Hours a pending account-deletion request stays live before the scheduled
    | purge permanently removes the account. Admin instant-purge skips this.
    |
    */
    'account_deletion_grace_hours' => env('ACCOUNT_DELETION_GRACE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Activity Log Export Threshold
    |--------------------------------------------------------------------------
    |
    | Filtered activity-log exports up to this many rows stream straight to the
    | browser; anything larger is handed to the ExportActivityLog queued job so
    | the web request never hangs building a huge file.
    |
    */
    'activity_log_export_queue_threshold' => env('ACTIVITY_LOG_EXPORT_QUEUE_THRESHOLD', 5000),

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

    /*
    |--------------------------------------------------------------------------
    | Auth & Frontend URLs
    |--------------------------------------------------------------------------
    |
    | Base URL for public frontend app (e.g. https://domain.com) and mode for
    | resolving authentication URLs (verification, password reset).
    | Modes: 'auto' (type-based: staff -> panel, app -> frontend), 'panel', 'frontend'.
    |
    */
    'frontend_url' => env('FRONTEND_URL', env('APP_URL')),
    'auth_url_mode' => env('AUTH_URL_MODE', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Application Features
    |--------------------------------------------------------------------------
    |
    | Defines available plan/subscription feature flags, types, and default values.
    |
    */
    'features' => [
        'device_limit' => [
            'name' => 'Device Limit',
            'type' => 'integer',
            'default' => 1,
        ],
        'ad_free' => [
            'name' => 'Ad Free',
            'type' => 'boolean',
            'default' => false,
        ],
    ],

];
