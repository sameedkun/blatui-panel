<?php

return [
    'title' => 'Webhook Notifications',
    'singular' => 'Webhook Notification',
    'subtitle' => 'Raw provider webhook notifications received for subscription billing events.',

    'breadcrumbs' => [
        'home' => 'Home',
        'webhook_notifications' => 'Webhook Notifications',
    ],

    'tab' => [
        'label' => 'Webhook Notifications',
    ],

    'actions' => [
        'view' => 'View',
        'process' => 'Process',
        'reprocess' => 'Reprocess',
    ],

    'dialogs' => [
        'redispatch_title' => 'Redispatch This Notification',
        'redispatch_description' => 'Re-fires the provider\'s webhook-received event with this notification\'s stored data, as if it had just arrived again. Any listener wired up to that event will run against it.',
    ],

    'toasts' => [
        'redispatched' => 'Notification redispatched.',
    ],

    'filters' => [
        'provider' => 'Provider',
        'processed' => 'Processed',
        'search' => 'Search transaction ID, product ID...',
        'clear' => 'Clear filters',
    ],

    'stats' => [
        'total' => 'Total',
        'total_description' => 'All notifications for this provider',
        'unprocessed' => 'Unprocessed',
        'unprocessed_description' => 'Awaiting processing',
        'today' => 'Today',
        'today_description' => 'Received in the last 24 hours',
    ],

    'table' => [
        'notification_type' => 'Type',
        'transaction_id' => 'Transaction ID',
        'product_id' => 'Product ID',
        'occurred_at' => 'Occurred',
        'processed' => 'Processed',
    ],

    'detail' => [
        'notification_type' => 'Notification Type',
        'subtype' => 'Subtype',
        'transaction_id' => 'Transaction ID',
        'original_transaction_id' => 'Original Transaction ID',
        'product_id' => 'Product ID',
        'environment' => 'Environment',
        'occurred_at' => 'Occurred At',
        'processed_at' => 'Processed At',
        'raw_payload' => 'Raw Payload',
    ],

    'empty' => [
        'notifications' => 'No webhook notifications found.',
        'subscription_notifications' => 'No webhook notifications linked to this subscription yet.',
        'select_provider' => 'Select a provider to view its notifications.',
    ],

    'values' => [
        'processed' => 'Processed',
        'unprocessed' => 'Unprocessed',
    ],
];
