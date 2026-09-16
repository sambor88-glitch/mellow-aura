<?php

namespace App\Modules\Consent\Support;

use App\Modules\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The visitor's cookie choice. Only Google Analytics needs consent, so there is one question: statistics
 * yes or no. Until someone answers yes, no Google script loads at all (Consent Mode, basic mode).
 * Without a measurement ID in the panel there is nothing to ask about and the banner stays hidden.
 */
class Consent
{
    public const COOKIE = 'mellowaura-consent';

    /** Raise it when the categories change, so everyone is asked again. */
    private const VERSION = 1;

    private const MONTHS = 12;

    public function __construct(private Settings $settings) {}

    public function measurementId(): ?string
    {
        $id = $this->settings->get('google_analytics_id');

        return is_string($id) && preg_match('/^G-[A-Z0-9]{4,20}$/', $id) ? $id : null;
    }

    /** There is something to ask about: Analytics is set up in the panel. */
    public function isNeeded(): bool
    {
        return $this->measurementId() !== null;
    }

    public function decided(Request $request): bool
    {
        return $this->choice($request) !== null;
    }

    public function allowsAnalytics(Request $request): bool
    {
        return $this->isNeeded() && ($this->choice($request)['analytics'] ?? false);
    }

    /**
     * When the visitor chose, for „Twój wybór z 16 września 2026”.
     */
    public function decidedAt(Request $request): ?Carbon
    {
        return $this->choice($request)['at'] ?? null;
    }

    public function cookie(bool $analytics): Cookie
    {
        return cookie(self::COOKIE, self::VERSION.'.'.($analytics ? 'analytics' : 'necessary').'.'.now()->timestamp, self::MONTHS * 30 * 24 * 60);
    }

    /**
     * Expired copies of the Analytics cookies the browser sent, for this host and its parent domain,
     * because Google writes them on the widest domain it can.
     *
     * @return list<Cookie>
     */
    public function forgetAnalyticsCookies(Request $request): array
    {
        $names = array_filter(array_keys($request->cookies->all()), fn (string $name) => $name === '_ga' || str_starts_with($name, '_ga_'));
        $domains = array_unique([null, '.'.implode('.', array_slice(explode('.', $request->getHost()), -2))]);

        $cookies = [];

        foreach ($names as $name) {
            foreach ($domains as $domain) {
                $cookies[] = cookie()->forget($name, '/', $domain);
            }
        }

        return $cookies;
    }

    /**
     * @return array{analytics: bool, at: Carbon}|null
     */
    private function choice(Request $request): ?array
    {
        $parts = explode('.', (string) $request->cookie(self::COOKIE));

        if (count($parts) !== 3 || $parts[0] !== (string) self::VERSION || ! in_array($parts[1], ['analytics', 'necessary'], true) || ! ctype_digit($parts[2])) {
            return null;
        }

        return ['analytics' => $parts[1] === 'analytics', 'at' => Carbon::createFromTimestamp((int) $parts[2], config('app.timezone'))];
    }
}
