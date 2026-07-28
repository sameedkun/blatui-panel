<?php

return [
    'title' => 'Users',
    'singular' => 'User',
    'subtitle' => 'Manage application end users, accounts, and subscriptions.',
    'actions' => [
        'create' => 'Create User',
        'edit' => 'Edit User',
        'ban' => 'Ban User',
        'unban' => 'Unban User',
        'delete' => 'Delete Account',
        'restore' => 'Restore Account',
        'purge' => 'Instant Purge',
        'assign_plan' => 'Assign / Change Plan',
        'cancel_subscription' => 'Cancel Subscription',
        'reactivate_subscription' => 'Reactivate Subscription',
    ],
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'status' => 'Status',
        'plan' => 'Plan',
        'type' => 'Type',
        'external_id' => 'External ID',
        'created_at' => 'Joined Date',
    ],
    'dialogs' => [
        'ban_title' => 'Ban User Account',
        'ban_reason_label' => 'Reason for banning',
        'unban_title' => 'Unban User Account',
        'delete_title' => 'Request Account Deletion',
        'purge_title' => 'Permanently Purge Account',
        'purge_warning' => 'This action is immediate and cannot be undone.',
    ],
];
