<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // The map of InPost lockers on the checkout. Without a token the customer types the locker's code herself.
    'inpost' => [
        'geowidget_token' => env('INPOST_GEOWIDGET_TOKEN'),
        'geowidget_sandbox' => (bool) env('INPOST_GEOWIDGET_SANDBOX', false),
        // ShipX (Shipping module): parcels and labels from the panel. Without the token and the organization id
        // the panel has no „Nadaj w InPost” and Kasia sends parcels from InPost Manager, as before.
        'shipx_token' => env('INPOST_SHIPX_TOKEN'),
        'shipx_organization_id' => env('INPOST_SHIPX_ORGANIZATION_ID'),
        'shipx_sandbox' => (bool) env('INPOST_SHIPX_SANDBOX', false),
    ],

    // Google Analytics Measurement Protocol (Checkout\Support\MeasurementProtocol, MA-264): the purchase told from the
    // server. The measurement ID comes from the panel; without the secret nothing is sent.
    'ga4' => [
        'api_secret' => env('GA4_API_SECRET'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    // Przelewy24 (Payments\Gateways\Przelewy24Gateway). Without the merchant id, CRC and API key the shop pays itself.
    'przelewy24' => [
        'merchant_id' => env('PRZELEWY24_MERCHANT_ID'),
        'pos_id' => env('PRZELEWY24_POS_ID'),
        'crc' => env('PRZELEWY24_CRC'),
        'api_key' => env('PRZELEWY24_API_KEY'),
        'sandbox' => (bool) env('PRZELEWY24_SANDBOX', false),
        // BLIK code typed in the shop (Level 0). False sends BLIK to Przelewy24's page until they switch Level 0 on.
        'blik_on_site' => (bool) env('PRZELEWY24_BLIK_ON_SITE', true),
        // Apple Pay and Google Pay are switched on per account by Przelewy24 (MA-98). Until they are, the checkout
        // does not promise them.
        'wallets' => (bool) env('PRZELEWY24_WALLETS', false),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
