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
    | Extra Model Paths
    |--------------------------------------------------------------------------
    |
    | Model discovery automatically scans all Composer PSR-4 namespaces
    | that contain "Models" (e.g. app/Models, vendor package Models/).
    | Add extra paths here only if your models live outside that convention.
    |
    */
    'extra_model_paths' => [
        //
    ],
];
