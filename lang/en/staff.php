<?php

return [
    'title' => 'Staff Members',
    'singular' => 'Staff Member',
    'subtitle' => 'Manage admin panel operators, assign RBAC roles, and control staff access.',
    'actions' => [
        'create' => 'Create Staff Member',
        'edit' => 'Edit Staff',
        'ban' => 'Suspend Staff Access',
        'unban' => 'Restore Staff Access',
        'delete' => 'Delete Account',
    ],
    'fields' => [
        'name' => 'Full Name',
        'email' => 'Email Address',
        'roles' => 'Assigned Roles',
        'status' => 'Status',
    ],
];
