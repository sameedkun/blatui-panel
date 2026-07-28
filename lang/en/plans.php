<?php

return [
    'title' => 'Plans',
    'singular' => 'Plan',
    'subtitle' => 'Manage subscription tiers, pricing, features, and provider IDs.',
    'actions' => [
        'create' => 'Create Plan',
        'edit' => 'Edit Plan',
        'delete' => 'Delete Plan',
        'retire' => 'Retire Plan',
    ],
    'fields' => [
        'name' => 'Plan Name',
        'slug' => 'Slug',
        'description' => 'Description',
        'is_active' => 'Active Status',
        'is_best_deal' => 'Best Deal Badge',
        'sort_order' => 'Sort Order',
        'prices' => 'Prices',
        'features' => 'Included Features',
    ],
];
