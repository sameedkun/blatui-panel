<?php

return [
    'user_type' => [
        'App' => 'App User',
        'Staff' => 'Staff',
        'Guest' => 'Guest',
    ],

    'subscription_status' => [
        'Trialing' => 'Trialing',
        'Active' => 'Active',
        'Grace' => 'Grace Period',
        'Cancelled' => 'Cancelled',
        'Expired' => 'Expired',
        'Failed' => 'Failed',
    ],

    'ticket_status' => [
        'Open' => 'Open',
        'Pending' => 'Pending',
        'Resolved' => 'Resolved',
        'Closed' => 'Closed',
    ],

    'ticket_priority' => [
        'Low' => 'Low',
        'Medium' => 'Medium',
        'High' => 'High',
        'Urgent' => 'Urgent',
    ],

    'feedback_status' => [
        'New' => 'New',
        'Read' => 'Read',
        'Resolved' => 'Resolved',
        'Ignored' => 'Ignored',
    ],

    'feedback_type' => [
        'General' => 'General',
        'Bug' => 'Bug',
        'Feature' => 'Feature Request',
        'Complaint' => 'Complaint',
        'Other' => 'Other',
    ],

    'notification_push_status' => [
        'Draft' => 'Draft',
        'Pending' => 'Pending',
        'Sent' => 'Sent',
        'Failed' => 'Failed',
    ],

    'notification_type' => [
        'General' => 'General',
        'Announcement' => 'Announcement',
        'Promotional' => 'Promotional',
        'Alert' => 'Alert',
    ],

    'device_type' => [
        'Mobile' => 'Mobile',
        'Tablet' => 'Tablet',
        'Desktop' => 'Desktop',
        'Web' => 'Web',
    ],

    'billing_interval' => [
        'Day' => 'Day',
        'Week' => 'Week',
        'Month' => 'Month',
        'Year' => 'Year',
    ],

    'billing_interval_count' => [
        'Day' => '{1} :count Day|[2,*] :count Days',
        'Week' => '{1} :count Week|[2,*] :count Weeks',
        'Month' => '{1} :count Month|[2,*] :count Months',
        'Year' => '{1} :count Year|[2,*] :count Years',
    ],

    'payment_provider' => [
        'Local' => 'Local',
        'Stripe' => 'Stripe',
        'AppStore' => 'App Store',
        'PlayStore' => 'Play Store',
        'Oxapay' => 'OxaPay',
    ],

    'cancelled_by' => [
        'User' => 'User',
        'Admin' => 'Administrator',
        'System' => 'System',
    ],

    'receipt_type' => [
        'Initial' => 'Initial',
        'Renewal' => 'Renewal',
        'Restore' => 'Restore',
        'Refund' => 'Refund',
        'Cancellation' => 'Cancellation',
    ],

    'device_type' => [
        'Mobile' => 'Mobile',
        'Tablet' => 'Tablet',
        'Desktop' => 'Desktop',
        'Web' => 'Web',
    ],
];
