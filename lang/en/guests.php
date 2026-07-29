<?php

return [
    'title' => 'Guests',
    'singular' => 'Guest',
    'subtitle' => 'Manage temporary guest accounts, conversions, and account merges.',
    'subtitle_form' => 'Manage temporary guest accounts and their lifecycle.',
    'price_option' => ':currency :amount / :interval',

    'actions' => [
        'convert' => 'Convert to App User',
        'merge' => 'Merge into Existing Account',
        'purge' => 'Purge Guest',
        'ban' => 'Ban',
        'unban' => 'Unban',
        'delete' => 'Delete',
        'restore' => 'Restore',
        'force_delete' => 'Permanently Delete',
        'view_profile' => 'View profile',
        'assign_plan' => 'Assign / Change Plan',
        'cancel_subscription' => 'Cancel Subscription',
        'reactivate_subscription' => 'Reactivate Subscription',
        'cancel_immediately' => 'Cancel Immediately',
        'cancel_at_period_end' => 'Cancel at Period End',
        'clear_selection' => 'Clear selection',
    ],

    'tabs' => [
        'overview' => 'Overview',
        'subscriptions' => 'Subscriptions',
        'activity' => 'Activity',
    ],

    'overview' => [
        'general' => 'General',
        'dates' => 'Dates',
    ],

    'billing_intervals' => [
        'day' => '{1} :count day|[2,*] :count days',
        'week' => '{1} :count week|[2,*] :count weeks',
        'month' => '{1} :count month|[2,*] :count months',
        'year' => '{1} :count year|[2,*] :count years',
    ],

    'stats' => [
        'total_guests' => 'Total Guests',
        'all_registered_accounts' => 'All registered guest accounts',
        'active' => 'Active',
        'not_banned' => 'Not banned',
        'banned' => 'Banned',
        'banned_accounts' => 'Banned accounts',
        'new_this_month' => 'New This Month',
        'joined_this_month' => 'Joined this month',
    ],

    'filters' => [
        'status' => 'Status',
        'registered' => 'Registered',
        'registered_from' => 'Registered from',
        'registered_to' => 'Registered to',
        'active' => 'Active',
        'banned' => 'Banned',
    ],

    'fields' => [
        'name' => 'Guest Name',
        'email' => 'Email',
        'status' => 'Status',
        'created_at' => 'First Seen',
        'registered' => 'Registered',
        'last_login' => 'Last Login',
        'guest_id' => 'Guest ID',
        'full_name' => 'Full Name',
        'external_id' => 'External ID',
        'plan' => 'Plan',
    ],

    'status_labels' => [
        'active' => 'Active',
        'banned' => 'Banned',
        'deleted' => 'Deleted',
        'never' => 'Never',
        'no_guests_found' => 'No guests found.',
        'clear_filters' => 'Clear filters',
        'selected' => 'selected',
        'free' => 'Free',
        'coming_soon' => 'Coming soon',
    ],

    'defaults' => [
        'ban_reason' => 'Banned by administrator.',
    ],

    'lifecycle_states' => [
        'active' => 'active',
        'pending' => 'pending deletion',
        'trashed' => 'deleted',
    ],

    'errors' => [
        'action_unavailable' => 'This action is not available while the account is :state.',
    ],

    'placeholders' => [
        'email' => 'you@example.com',
        'full_name' => 'Full name',
    ],

    'validation' => [
        'plan_required' => 'Select a plan.',
        'price_required' => 'Select a price.',
        'convert_email_required' => 'Enter an email address.',
        'convert_email_email' => 'Enter a valid email address.',
        'convert_email_unique' => 'This email address is already in use.',
        'merge_destination_required' => 'Select a destination account.',
        'merge_reason_required' => 'Enter a reason for the merge.',
    ],

    'dialogs' => [
        'ban_guest' => 'Ban Guest',
        'ban_guest_desc' => 'Optionally provide a reason. Defaults to "Banned by administrator."',
        'ban_reason_placeholder' => 'Reason for the ban (optional)',
        'delete_guest' => 'Delete Guest',
        'delete_guest_desc' => 'This permanently deletes the guest and all associated data. This cannot be undone.',
        'restore_guest' => 'Restore Guest',
        'restore_guest_desc' => 'This will restore the guest\'s account.',
        'force_delete_guest' => 'Permanently Delete',
        'force_delete_guest_desc' => 'This action cannot be undone. The guest and all associated data will be permanently removed.',
        'convert_title' => 'Convert to App User',
        'convert_desc' => 'This guest becomes a real app user in place — same account, same history. They\'ll receive a password-reset link to set their own credentials.',
        'mark_email_verified' => 'Mark email as verified',
        'mark_email_verified_help' => 'Skips the verification email — use only when the admin already confirmed this address belongs to the account holder.',
        'merge_title' => 'Merge into Existing Account',
        'merge_desc' => 'Merges this guest\'s identity into another app account. The guest record is permanently removed; the destination account survives. This cannot be undone.',
        'merge_destination' => 'Destination account',
        'merge_search_placeholder' => 'Search app users by name or email...',
        'merge_reason' => 'Reason',
        'merge_reason_placeholder' => 'Why are these the same person?',
        'no_candidates_found' => 'No app users found.',
        'change_destination' => 'Change',

        // Bulk dialogs
        'bulk_ban_title' => 'Ban :count Guests',
        'bulk_unban_title' => 'Unban :count Guests',
        'bulk_unban_desc' => 'This will lift the ban on all selected guests.',
        'bulk_delete_title' => 'Delete :count Guests',
        'bulk_delete_desc' => 'This permanently deletes all selected guests and their associated data. This cannot be undone.',
        'bulk_restore_title' => 'Restore :count Guests',
        'bulk_restore_desc' => 'All selected deleted guests will be restored.',
        'bulk_force_delete_title' => 'Permanently Delete :count Guests',
        'bulk_force_delete_desc' => 'This action cannot be undone. All selected guests will be permanently removed.',
    ],

    'toasts' => [
        'guest_banned' => 'Guest :name has been banned.',
        'guest_unbanned' => ':name has been unbanned.',
        'guest_deleted' => ':name has been permanently deleted.',
        'guest_restored' => ':name has been restored.',
        'guest_permanently_deleted' => ':name has been permanently deleted.',
        'guest_converted' => ':name has been converted to an app user.',
        'guest_merged' => ':guest has been merged into :destination.',
        'bulk_banned' => ':count guests banned.',
        'bulk_unbanned' => ':count guests unbanned.',
        'bulk_deleted' => ':count guests permanently deleted.',
        'bulk_restored' => ':count guests restored.',
        'bulk_permanently_deleted' => ':count guests permanently deleted.',
        'no_active_subscription' => 'This guest has no active subscription.',
        'plan_assigned' => ':name is now on the :plan plan.',
        'subscription_cancelled_immediately' => ':plan subscription cancelled immediately.',
        'subscription_cancelled_period_end' => ':plan subscription will end on :date.',
        'subscription_reactivated' => ':plan subscription reactivated.',
        'subscription_cannot_reactivate' => 'This guest has no cancelled subscription that can be reactivated.',
    ],
];
