<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Without realpath(): the framework default turns into false when the
    | directory is missing (fresh shared storage on Forge), and view:cache
    | then fails with "View path not found". Blade creates the directory itself.
    |
    */

    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),

];
