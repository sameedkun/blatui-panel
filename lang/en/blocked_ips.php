<?php

return [
    'title' => 'Blocked IPs',
    'singular' => 'Blocked IP',
    'subtitle' => 'Block an IP globally or for a single user, with optional expiry and hit tracking.',

    'actions' => [
        'create' => 'Block IP',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'purge_expired' => '{1} Delete :count Expired|[2,*] Delete :count Expired',
        'inspect' => 'Who’s Behind This IP',
        'clear_selection' => 'Clear selection',
        'selected' => '{1} :count selected|[2,*] :count selected',
        'save_changes' => 'Save Changes',
        'cancel' => 'Cancel',
        'close' => 'Close',
    ],

    'fields' => [
        'ip_address' => 'IP Address',
        'scope' => 'Scope',
        'reason' => 'Reason',
        'created_by' => 'Created By',
        'hits' => 'Hits',
        'last_hit' => 'Last Hit',
        'expires' => 'Expires',
        'expires_at' => 'Expires At',
        'user_email' => 'User Email',
    ],

    'scopes' => [
        'global' => 'Global',
        'global_block' => 'Global Block',
        'global_every_user' => 'Global (every user)',
        'per_user' => 'Per-User',
        'per_user_block' => 'Per-User Block',
    ],

    'filters' => [
        'expiry' => 'Expiry',
        'search' => 'Search IP address...',
        'clear' => 'Clear filters',
    ],

    'stats' => [
        'total' => 'Total Blocks',
        'total_description' => 'All rules',
        'active' => 'Active',
        'active_description' => 'Currently enforced',
        'expired' => 'Expired',
        'expired_description' => 'Prunable',
        'global' => 'Global',
        'global_description' => 'Block every user',
    ],

    'status' => [
        'expired' => 'Expired',
        'not_expired' => 'Not Expired',
        'permanent' => 'Permanent',
        'never' => 'Never',
        'system' => 'System',
        'unknown_user' => 'Unknown user',
        'unknown_platform' => 'Unknown platform',
        'user_number' => 'User #:id',
    ],

    'empty' => [
        'blocked_ips' => 'No blocked IPs found.',
        'devices' => 'No devices have been seen on this IP.',
        'users' => 'No app users found.',
        'users_matching' => 'No app users found matching “:search”.',
    ],

    'form' => [
        'create_title' => 'Block IP Address',
        'edit_title' => 'Edit Block',
        'create_description' => 'Block traffic from a single IP address, globally or for one account.',
        'edit_description' => 'Update the reason or expiry for this block.',
        'breadcrumb_create' => 'Block IP',
        'breadcrumb_edit' => 'Edit',
        'change_user' => 'Change User',
        'search_users' => 'Search app users by name or email...',
        'reason_placeholder' => 'Why is this IP being blocked?',
        'global_warning_title' => 'This blocks every account using this IP',
        'distinct_accounts' => '{1} :count distinct account seen on this IP in the last 30 days.|[2,*] :count distinct accounts seen on this IP in the last 30 days.',
        'carrier_nat_warning' => 'This looks like a shared carrier IP (for example, mobile-network NAT) — blocking it may lock out many unrelated, legitimate users.',
        'global_confirmation' => 'I understand and want to block this IP for every user.',
        'permanent' => 'Permanent (no expiry)',
        'expires_description' => 'Defaults to 7 days out — permanent blocks accumulate, so make that an explicit choice.',
        'saving' => 'Saving...',
        'blocking' => 'Blocking...',
    ],

    'dialogs' => [
        'delete_title' => 'Delete Block',
        'delete_description' => 'This immediately stops enforcing the block. Traffic from this IP will be allowed through again. This action cannot be undone.',
        'expired_title' => 'Delete :count Expired Blocks',
        'expired_description' => 'Every block whose expiry has already passed will be permanently removed. This action cannot be undone.',
        'bulk_delete_title' => 'Delete :count Blocks',
        'bulk_delete_description' => 'Every selected block will be permanently removed. This action cannot be undone.',
        'delete_all' => 'Delete All',
    ],

    'activity' => [
        'summary' => '{1} :count distinct account seen on this IP in the last 30 days.|[2,*] :count distinct accounts seen on this IP in the last 30 days.',
        'device_details' => ':device · :platform · last seen :last_seen',
    ],

    'validation' => [
        'ip_required' => 'Enter an IP address.',
        'ip_invalid' => 'Enter a valid IPv4 or IPv6 address.',
        'scope_required' => 'Select a block scope.',
        'scope_invalid' => 'Select a valid block scope.',
        'user_email_required' => 'Select a user for a per-user block.',
        'user_email_invalid' => 'Enter a valid user email address.',
        'reason_invalid' => 'The reason must be text.',
        'reason_max' => 'The reason may not be greater than :max characters.',
        'permanent_invalid' => 'The permanent selection is invalid.',
        'expires_required' => 'Enter an expiry date or choose a permanent block.',
        'expires_invalid' => 'Enter a valid expiry date.',
        'expires_future' => 'The expiry date must be in the future.',
        'confirm_global' => 'Confirm the global-scope warning before blocking every user on this IP.',
        'user_not_found' => 'No account found with that email address.',
        'duplicate_global' => 'This IP already has a global block.',
        'duplicate_user' => 'This IP is already blocked for this user.',
    ],

    'validation_attributes' => [
        'ip_address' => 'IP address',
        'scope' => 'scope',
        'user_email' => 'user email',
        'reason' => 'reason',
        'permanent' => 'permanent status',
        'expires_at' => 'expiry date',
    ],

    'toasts' => [
        'created' => 'IP address blocked.',
        'updated' => 'Block updated.',
        'deleted' => 'Block on :ip removed.',
        'bulk_deleted' => ':count blocks deleted.',
        'expired_deleted' => ':count expired blocks deleted.',
    ],
];
