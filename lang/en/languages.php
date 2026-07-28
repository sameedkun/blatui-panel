<?php

return [
    'title' => 'Languages',
    'singular' => 'Language',
    'subtitle' => 'Manage system locales, active languages, and localization defaults.',
    'actions' => [
        'create' => 'Add Language',
        'edit' => 'Edit Language',
        'delete' => 'Remove Language',
    ],
    'fields' => [
        'name' => 'Language Name',
        'code' => 'Locale Code',
        'flag' => 'Flag Icon',
        'direction' => 'Text Direction (LTR/RTL)',
        'is_active' => 'Active Status',
        'is_default' => 'Default Locale',
    ],
];
