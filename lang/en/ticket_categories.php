<?php

return [
    'title' => 'Ticket Categories',
    'singular' => 'Category',
    'subtitle' => 'Manage ticket categories and agent assignment pools.',
    'actions' => [
        'create' => 'Create Category',
        'edit' => 'Edit Category',
        'delete' => 'Delete Category',
    ],
    'fields' => [
        'name' => 'Category Name',
        'slug' => 'Slug',
        'description' => 'Description',
        'assigned_agents' => 'Eligible Support Agents',
        'is_active' => 'Active Status',
    ],
];
