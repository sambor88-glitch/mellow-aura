<?php

/*
 * The staging site behind a password (Shared\Http\Middleware\ProtectStaging). Empty STAGING_PASSWORD switches the
 * protection off — locally and in production.
 */
return [
    'user' => env('STAGING_USER', 'mellowaura'),
    'password' => env('STAGING_PASSWORD', ''),
];
