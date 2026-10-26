<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Localization\Support\Locales;

/**
 * InPost's map of parcel lockers (Geowidget v5) on the checkout. The page loads it only when the customer opens
 * the map, so nobody who picks the courier or leaves the choice to Kasia talks to InPost's servers.
 *
 * The token is issued in InPost Manager for the shop's domain; without one there is no map and the customer
 * types the locker's code, or leaves it empty and Kasia agrees on one with the customer.
 */
class LockerMap
{
    private const HOST = 'https://geowidget.inpost.pl';

    private const SANDBOX_HOST = 'https://sandbox-easy-geowidget-sdk.easypack24.net';

    /**
     * What resources/js/checkout.js needs to draw the map, or null when the shop has no token.
     *
     * @return array{token: string, script: string, style: string, language: string}|null
     */
    public static function config(): ?array
    {
        $token = (string) config('services.inpost.geowidget_token');

        if ($token === '') {
            return null;
        }

        $host = config('services.inpost.geowidget_sandbox') ? self::SANDBOX_HOST : self::HOST;

        return [
            'token' => $token,
            'script' => $host.'/inpost-geowidget.js',
            'style' => $host.'/inpost-geowidget.css',
            // The map speaks Polish and English; the English page gets English.
            'language' => Locales::current() === 'en' ? 'en' : 'pl',
        ];
    }
}
