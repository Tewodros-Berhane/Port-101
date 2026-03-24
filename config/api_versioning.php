<?php

return [
    'default_version' => 'v1',

    'versions' => [
        'v1' => [
            'status' => env('API_V1_STATUS', 'stable'),
            'deprecation_at' => env('API_V1_DEPRECATION_AT'),
            'sunset_at' => env('API_V1_SUNSET_AT'),
            'change_policy' => [
                'breaking' => 'requires_new_version',
                'non_breaking' => 'allowed_in_place',
            ],
            'changelog_categories' => [
                'breaking',
                'additive',
                'behavioral',
                'bugfix',
                'operational',
            ],
        ],
    ],
];
