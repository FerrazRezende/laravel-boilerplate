<?php

return [
    /*
    |--------------------------------------------------------------------------
    | First Deploy Date
    |--------------------------------------------------------------------------
    |
    | Date of the first production deploy. Features whose implemented_at is on
    | or before this date are active by default in production.
    | In dev/local, every implemented feature is active.
    |
    */
    'first_deploy_date' => env('FEATURES_FIRST_DEPLOY_DATE'),

    /*
    |--------------------------------------------------------------------------
    | Feature Definitions
    |--------------------------------------------------------------------------
    |
    | Add your application's feature flags here.
    | Each feature has a display name, description, and optional implemented_at date.
    |
    | Example:
    |   'my-feature' => [
    |       'name' => 'My Feature',
    |       'description' => 'Description of the feature',
    |       'implemented_at' => null, // or a date string like '2025-01-01'
    |   ],
    |
    */
    'definitions' => [],
];
