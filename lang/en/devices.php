<?php

return [
    'title' => 'Devices',
    'singular' => 'Device',
    'subtitle' => 'Track registered devices, active access tokens, and revoke access.',
    'actions' => [
        'revoke' => 'Revoke Access',
        'revoke_all' => 'Revoke All Devices',
        'block' => 'Block Device',
        'unblock' => 'Unblock Device',
        'shared_fingerprints' => 'Shared Fingerprints',
    ],
    'fields' => [
        'user' => 'User',
        'device_name' => 'Device Name',
        'device_type' => 'Type',
        'platform' => 'Platform / OS',
        'ip_address' => 'IP Address',
        'last_active' => 'Last Active',
    ],
];
