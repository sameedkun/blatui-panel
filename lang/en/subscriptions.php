<?php

return [
    'title' => 'Subscriptions',
    'singular' => 'Subscription',
    'subtitle' => 'Global view of all active, trialing, and historical subscriptions.',
    'stats' => [
        'total' => 'Total Subscriptions',
        'active' => 'Active Subscriptions',
        'cancelled' => 'Cancelled Subscriptions',
        'revenue' => 'Revenue Collected',
    ],
    'actions' => [
        'cancel' => 'Cancel Subscription',
        'reactivate' => 'Reactivate',
    ],
    'fields' => [
        'user' => 'Subscriber',
        'plan' => 'Plan',
        'status' => 'Status',
        'provider' => 'Provider',
        'starts_at' => 'Started At',
        'ends_at' => 'Ends At',
        'trial_ends_at' => 'Trial Ends',
        'grace_ends_at' => 'Grace Ends',
    ],
];
