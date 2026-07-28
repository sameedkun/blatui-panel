<?php

return [
    'title' => 'Guests',
    'singular' => 'Guest',
    'subtitle' => 'Manage temporary guest accounts, conversions, and account merges.',
    'actions' => [
        'convert' => 'Convert to User',
        'merge' => 'Merge Account',
        'purge' => 'Purge Guest',
    ],
    'fields' => [
        'name' => 'Guest Name',
        'email' => 'Email',
        'created_at' => 'First Seen',
    ],
    'dialogs' => [
        'convert_title' => 'Convert Guest to Registered User',
        'merge_title' => 'Merge Guest into Existing Account',
        'merge_destination' => 'Target Account Email',
        'merge_reason' => 'Reason for merge',
    ],
];
