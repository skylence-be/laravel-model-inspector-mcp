<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Server
    |--------------------------------------------------------------------------
    */
    'server' => [
        'name' => 'Laravel Eloquent',
        'version' => '1.0.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools
    |--------------------------------------------------------------------------
    |
    | Enable or disable individual MCP tools.
    |
    */
    'tools' => [
        'model-discovery' => true,
        'model-inspector' => true,
        'relationship-map' => true,
        'schema-inspector' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for Eloquent models. By default only app/Models
    | is scanned. Add vendor package paths to discover package models.
    |
    | Example: 'vendor/skylence/erp/src/Models'
    |
    */
    'model_paths' => [
        'app/Models',
    ],
];
