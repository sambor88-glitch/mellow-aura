<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staging protection
    |--------------------------------------------------------------------------
    |
    | When a password is set, every request needs HTTP basic auth and every
    | response gets X-Robots-Tag: noindex. Set it only on the staging site.
    |
    */

    'user' => env('STAGING_USER', 'mellowaura'),

    'password' => env('STAGING_PASSWORD', ''),

];
