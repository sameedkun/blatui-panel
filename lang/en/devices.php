<?php

return [
    'title' => 'Devices',
    'singular' => 'Device',
    'subtitle' => 'Every device ever registered, across every account.',

    'actions' => [
        'revoke' => 'Revoke Access',
        'revoke_short' => 'Revoke',
        'revoke_all' => 'Revoke All Devices',
        'block' => 'Block Device',
        'block_short' => 'Block',
        'unblock' => 'Unblock Device',
        'unblock_short' => 'Unblock',
        'shared_fingerprints' => 'Shared Fingerprints',
        'export_csv' => 'Export CSV',
        'actions' => 'Actions',
        'clear_filters' => 'Clear filters',
    ],

    'fields' => [
        'user' => 'User',
        'device_name' => 'Device Name',
        'device_type' => 'Device Type',
        'platform' => 'Platform',
        'ip_address' => 'IP Address',
        'last_active' => 'Last Active',
        'last_seen' => 'Last Seen',
        'app_version' => 'App Version',
        'country' => 'Country',
        'status' => 'Status',
        'fingerprint' => 'Fingerprint',
        'location_ip' => 'Location / IP',
    ],

    'stats' => [
        'total' => 'Total Devices',
        'ever_registered' => 'Ever registered',
        'currently_signed_in' => 'Currently signed in',
        'revoked_description' => 'Signed out, may reactivate',
        'blocked_description' => 'Cannot reactivate by login',
    ],

    'status' => [
        'active' => 'Active',
        'revoked' => 'Revoked',
        'blocked' => 'Blocked',
        'unnamed_device' => 'Unnamed device',
        'unknown_type' => 'Unknown type',
        'never_seen' => 'Never seen',
        'never' => 'Never',
        'none_found' => 'No devices found.',
    ],

    'placeholders' => [
        'fingerprint' => 'Raw client value',
        'block_reason' => 'Why is this device being blocked?',
    ],

    'dialogs' => [
        'block_title' => 'Block Device',
        'block_description' => 'A blocked device is signed out immediately and can never be reactivated by logging in again — only an admin can lift the block.',
        'reason' => 'Reason',
        'revoke_title' => 'Revoke Device',
        'revoke_description' => 'This immediately signs the device out. It can log back in again later, subject to the account\'s device limit.',
    ],

    'validation' => [
        'block_reason_required' => 'A reason is required to block a device.',
        'block_reason_min' => 'The reason must be at least :min characters.',
    ],

    'toasts' => [
        'blocked' => ':name has been blocked.',
        'unblocked' => ':name has been unblocked.',
        'unblocked_description' => 'The user must log in again to reconnect it.',
        'revoked' => ':name has been revoked.',
    ],

    'shared' => [
        'title' => 'Shared Fingerprints',
        'description' => 'Device fingerprints currently attached to more than one account — a possible sign of account sharing or a spoofed client.',
        'accounts' => 'Accounts',
        'shared_by' => 'Shared By',
        'more' => '+:count more',
        'none_found' => 'No shared fingerprints found.',
    ],

    'csv' => [
        'user' => 'User',
        'name' => 'Name',
        'platform' => 'Platform',
        'os' => 'OS',
        'device_type' => 'Device Type',
        'app_version' => 'App Version',
        'country' => 'Country',
        'ip_address' => 'IP Address',
        'status' => 'Status',
        'last_seen' => 'Last Seen',
    ],
];
