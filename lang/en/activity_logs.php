<?php

return [
    'title' => 'Activity Logs',
    'singular' => 'Audit Entry',
    'subtitle' => 'System-wide audit trail of administrative, system, and auth events.',
    'actions' => [
        'export' => 'Export Audit Log (CSV)',
        'view_full_history' => 'View full history',
    ],
    'status_labels' => [
        'no_activity' => 'No activity recorded for this account yet.',
    ],
    'fields' => [
        'causer' => 'Causer / Actor',
        'subject' => 'Subject Record',
        'module' => 'Module',
        'action' => 'Action Verb',
        'context' => 'Runtime Context',
        'created_at' => 'Timestamp',
        'changes' => 'Attribute Changes',
    ],
];
