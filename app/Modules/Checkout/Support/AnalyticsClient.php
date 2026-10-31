<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Consent\Support\Consent;
use Illuminate\Http\Request;

/**
 * What an order keeps for the purchase measured from the server (MA-264): Google Analytics' id of this browser
 * from the _ga cookie and its current session from the _ga_<ID> cookie. Only with the customer's yes to statistics —
 * without it the order keeps nothing and Google Analytics never hears about it from the server.
 *
 * Google's script writes these cookies itself, so they are read from the raw Cookie header: the shop's cookie
 * encryption does not know them and would drop them.
 */
class AnalyticsClient
{
    public function __construct(private Consent $consent) {}

    /**
     * @return array{ga_client_id: ?string, ga_session_id: ?string, analytics_ad_user_data: bool}
     */
    public function fromRequest(?Request $request): array
    {
        $none = ['ga_client_id' => null, 'ga_session_id' => null, 'analytics_ad_user_data' => false];

        if ($request === null || ! $this->consent->allowsAnalytics($request)) {
            return $none;
        }

        $cookies = self::rawCookies($request);
        $clientId = self::clientId($cookies['_ga'] ?? null);

        if ($clientId === null) {
            return $none;
        }

        $measurementId = (string) $this->consent->measurementId();

        return [
            'ga_client_id' => $clientId,
            'ga_session_id' => self::sessionId($cookies['_ga_'.substr($measurementId, 2)] ?? null),
            'analytics_ad_user_data' => $this->consent->allowsAdUserData($request),
        ];
    }

    /**
     * „GA1.1.1234567890.1700000000” → „1234567890.1700000000”.
     */
    public static function clientId(?string $cookie): ?string
    {
        return $cookie !== null && preg_match('/^GA\d\.\d+\.(\d{1,20}\.\d{1,20})$/', $cookie, $match) ? $match[1] : null;
    }

    /**
     * „GS1.1.1700000000.1.1…” (older) or „GS2.1.s1700000000$o1$g1…” (since 2025) → „1700000000”.
     */
    public static function sessionId(?string $cookie): ?string
    {
        if ($cookie === null) {
            return null;
        }

        if (preg_match('/^GS2\.\d+\.s(\d{1,20})(\$|$)/', $cookie, $match) || preg_match('/^GS1\.\d+\.(\d{1,20})(\.|$)/', $cookie, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function rawCookies(Request $request): array
    {
        $cookies = [];

        foreach (explode(';', (string) $request->headers->get('cookie')) as $pair) {
            [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, null);

            if ($name !== '' && $value !== null && str_starts_with($name, '_ga')) {
                $cookies[$name] = urldecode($value);
            }
        }

        return $cookies;
    }
}
