<?php

return [
    'title' => 'Activity Logs',
    'singular' => 'Audit Entry',
    'subtitle' => 'System-wide audit trail of administrative, system, and auth events.',
    'actions' => [
        'export' => 'Export Audit Log (CSV)',
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
