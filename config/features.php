<?php

return [
    /*
    |--------------------------------------------------------------------------
    | First Deploy Date
    |--------------------------------------------------------------------------
    |
    | Data do primeiro deploy em produção. Features com implemented_at
    | anterior ou igual a esta data são ativadas por padrão em prod.
    | Em dev/local, todas as features implementadas são ativadas.
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
