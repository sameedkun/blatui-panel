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
        'investigate',
        'block',
        'unblock',
        'revoke',
        'update',
        'create-global',
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

        // --- Core ---
        'dashboard' => [
            'label' => 'Dashboard',
            'group' => 'core',
            'actions' => ['view'],
            'icon' => 'layout-dashboard',
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
        'plans' => [
            'label' => 'Plans',
            'group' => 'management',
            'actions' => ['view', 'create', 'edit', 'delete', 'manage'],
            'icon' => 'credit-card',
        ],
        'subscriptions' => [
            'label' => 'Subscriptions',
            'group' => 'management',
            'actions' => ['view', 'manage'],
            'icon' => 'receipt',
        ],
        'devices' => [
            'label' => 'Devices',
            'group' => 'management',
            'actions' => ['view', 'investigate', 'block', 'unblock', 'revoke', 'export'],
            'icon' => 'smartphone',
        ],

        'blocked-ips' => [
            'label' => 'Blocked IPs',
            'group' => 'management',
            'actions' => ['view', 'create', 'create-global', 'update', 'delete'],
            'icon' => 'shield-alert',
        ],

        'webhook_notifications' => [
            'label' => 'Webhook Notifications',
            'group' => 'management',
            'actions' => ['view', 'manage'],
            'icon' => 'webhook',
        ],

        // --- Application ---
        'languages' => [
            'label' => 'Languages',
            'group' => 'app',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'icon' => 'globe',
        ],
        'feedback' => [
            'label' => 'Feedback',
            'group' => 'app',
            'actions' => ['view', 'manage'],
            'icon' => 'message-square-quote',
        ],
        'notifications' => [
            'label' => 'Notifications',
            'group' => 'app',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'icon' => 'bell',
        ],

        // --- Support ---
        'tickets' => [
            'label' => 'Tickets',
            'group' => 'support',
            'actions' => ['view', 'create', 'manage'],
            'icon' => 'life-buoy',
        ],
        'ticket_categories' => [
            'label' => 'Ticket Categories',
            'group' => 'support',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'icon' => 'tags',
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

        'logs' => [
            'label' => 'Log Viewer',
            'group' => 'settings',
            'actions' => ['access'],
            'icon' => 'file-text',
        ],
        'api_docs' => [
            'label' => 'API Docs',
            'group' => 'settings',
            'actions' => ['access'],
            'icon' => 'book-open',
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
        'core' => 'Core',
        'management' => 'Management',
        'infrastructure' => 'Infrastructure',
        'app' => 'Application',
        'support' => 'Support',
        'administration' => 'Administration',
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
        'api_docs.access',
        'activity_logs.export',
        // A global IP block can lock out thousands of legitimate users at once
        // (carrier-NAT), so creating one is reserved for senior staff only.
        'blocked-ips.create-global',
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
    | Ticket Auto-Close & Retention
    |--------------------------------------------------------------------------
    |
    | A Pending/Resolved ticket with no requester reply since the staff's last
    | message auto-closes once that staff message reaches this many days old.
    | A Closed ticket (and all its messages/attachments) is permanently purged
    | this many months after it was closed.
    |
    */
    'ticket_auto_close_inactive_days' => env('TICKET_AUTO_CLOSE_INACTIVE_DAYS', 7),
    'ticket_purge_closed_after_months' => env('TICKET_PURGE_CLOSED_AFTER_MONTHS', 6),

    /*
    |--------------------------------------------------------------------------
    | Devices & IP Blocking
    |--------------------------------------------------------------------------
    |
    | Months a revoked (never blocked) device row is kept before the monthly
    | retention sweep deletes it, and the distinct-user threshold above which
    | creating a global IP block warns that it looks like carrier NAT.
    |
    */
    'user_device_revoked_retention_months' => env('USER_DEVICE_REVOKED_RETENTION_MONTHS', 6),
    'blocked_ip_carrier_nat_threshold' => env('BLOCKED_IP_CARRIER_NAT_THRESHOLD', 10),

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
    | Bulk Account Action Queue Threshold
    |--------------------------------------------------------------------------
    |
    | A bulk force-delete/purge selection (Users or Guests index) up to this
    | many accounts runs inline in the request; anything larger is handed to a
    | queued job instead. Each account is its own DB transaction plus a
    | Storage::delete() call for its avatar, so a large selection running
    | synchronously risks blowing the request timeout.
    |
    */
    'bulk_account_action_queue_threshold' => env('BULK_ACCOUNT_ACTION_QUEUE_THRESHOLD', 100),

    /*
    |--------------------------------------------------------------------------
    | Bulk Account Action Selection Cap
    |--------------------------------------------------------------------------
    |
    | Hard ceiling on how many accounts a single bulk force-delete/purge action
    | (Users or Guests index) will accept at all, queued or not — past this,
    | the action is rejected outright rather than handed to a queue worker.
    | The queue threshold above only decides sync vs. async; this is what
    | actually stops an unbounded selection from becoming an unbounded job
    | (every row is a real network call to delete an avatar).
    |
    */
    'bulk_account_action_max_selection' => env('BULK_ACCOUNT_ACTION_MAX_SELECTION', 1000),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Metric Cache
    |--------------------------------------------------------------------------
    |
    | How long a dashboard widget's resolved payload is cached, in seconds. The
    | dashboard runs a lot of aggregates on every load, and none of them need to
    | be accurate to the second. Set to 0 to disable caching entirely (useful in
    | local development, where stale numbers are more confusing than slow ones).
    |
    */
    'dashboard_cache_seconds' => env('DASHBOARD_CACHE_SECONDS', 300),

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
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | The single source of truth for which locales the panel UI supports.
    | Adding a locale here (plus its lang/{code} translation files) is all
    | that's needed to wire it into the locale-switch route, the SetLocale
    | middleware, and the language-switcher component.
    |
    */
    'locales' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'flag' => '🇬🇧',
        ],
        'tr' => [
            'name' => 'Turkish',
            'native' => 'Türkçe',
            'flag' => '🇹🇷',
        ],
    ],

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
            'default' => 3,
        ],
        // Separate from device_limit: browser sessions (DeviceType::Web) are
        // disposable/numerous by nature (work computer, home, a kiosk...) and
        // don't compete with the user's app-device allowance. Crossing this
        // one evicts the oldest browser session instead of rejecting the
        // login — see DeviceService::enforceLimit().
        'browser_device_limit' => [
            'name' => 'Browser Session Limit',
            'type' => 'integer',
            'default' => 15,
        ],
        'ad_free' => [
            'name' => 'Ad Free',
            'type' => 'boolean',
            'default' => false,
        ],
    ],

];
