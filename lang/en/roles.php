<?php

return [
    'title' => 'Roles & Permissions',
    'singular' => 'Role',
    'subtitle' => 'Configure RBAC roles, permission sets, and system access rights.',
    'actions' => [
        'create' => 'Create Role',
        'edit' => 'Edit Role',
        'delete' => 'Delete Role',
    ],
    'fields' => [
        'name' => 'Role Name',
        'guard_name' => 'Guard',
        'permissions' => 'Permissions Matrix',
        'protected' => 'Protected System Role',
    ],
];
