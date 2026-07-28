<?php

return [
    'title' => 'Blocked IPs',
    'singular' => 'Blocked IP',
    'subtitle' => 'Manage security blocks for global IP addresses or specific accounts.',
    'actions' => [
        'create' => 'Block IP Address',
        'delete' => 'Remove Block',
        'purge_expired' => 'Clear Expired Blocks',
    ],
    'fields' => [
        'ip_address' => 'IP Address',
        'scope' => 'Scope',
        'global' => 'Global Block',
        'per_user' => 'Per-User Block',
        'reason' => 'Block Reason',
        'hits' => 'Hit Count',
        'expires_at' => 'Expires At',
    ],
    'warnings' => [
        'carrier_nat' => 'Warning: This IP address matches multiple distinct accounts. Blocking it globally may affect legitimate users on carrier NAT networks.',
        'global_confirm' => 'I understand that creating a global block affects all traffic matching this IP.',
    ],
];
