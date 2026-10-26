<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Technical alerts
    |--------------------------------------------------------------------------
    |
    | An error on the site, a mail that did not go out after every attempt, a queue worker or a scheduler
    | that stopped. The alerts go to the person who looks after the shop, not to Kasia: she sees the mails
    | that did not go out in the panel. Without an address the alerts only reach the log.
    |
    */

    'alert_email' => env('MONITORING_ALERT_EMAIL'),

    // An outside service pinged by the scheduler every minute, which raises the alarm when the pings stop,
    // for example a Forge heartbeat or healthchecks.io. It still works when the whole server is down.
    'heartbeat_url' => env('MONITORING_HEARTBEAT_URL'),

    // How long the scheduler or the queue worker may stay silent, and a job may wait, before it counts as stopped.
    'silence_minutes' => 10,

    // The same alert goes out at most once in this time, so a failure on every page view makes one mail.
    'repeat_after_minutes' => 60,

];
