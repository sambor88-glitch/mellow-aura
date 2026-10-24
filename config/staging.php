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

    /*
    |--------------------------------------------------------------------------
    | Review e-mail after a staging deploy
    |--------------------------------------------------------------------------
    |
    | `artisan staging:notify-review` runs at the end of the Forge deploy and
    | e-mails the reviewer the Review-Note trailers of the new commits. Empty
    | reviewer address turns it off. Addresses live only in the server .env.
    |
    */

    'review' => [
        'to' => env('STAGING_REVIEW_TO', ''),
        'reply_to' => env('STAGING_REVIEW_REPLY_TO', ''),
        'signature' => env('STAGING_REVIEW_SIGNATURE', ''),
        'repository' => base_path(),
        'state_file' => storage_path('app/staging-review/last-notified-commit'),
    ],

];
