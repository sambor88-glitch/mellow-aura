<?php

return [

    /*
    | The staging site's login (Shared\Http\Middleware\ProtectStaging). Set the password only on staging:
    | empty, the site is open, as it is locally and in production.
    */

    'user' => env('STAGING_USER', 'mellowaura'),

    'password' => env('STAGING_PASSWORD', ''),

];
