<?php

namespace App\Modules\Shared\Support;

use App\Modules\Settings\Settings;

/**
 * Delivery to an address in Poland for one thing at a given price, from the panel's delivery options: free from
 * the threshold, with how long it takes to leave the studio and to travel, as the checkout shows it under the option
 * („1–2 dni robocze”). Pickup at the studio is not a delivery. The product page's structured data and the feed
 * for Google Merchant Center both read it from here, so Google sees the same prices in both.
 */
final class DeliveryOffers
{
    public const PICKUP = 'studio_pickup';

    /**
     * Something made for the order, like a mug with the customer's own text, passes $withTimes false:
     * it is made first, so no delivery time is promised.
     *
     * @return list<array{label: string, price: int, handling: ?array{int, int}, transit: ?array{int, int}}>
     */
    public static function for(int $price, bool $withTimes, Settings $settings): array
    {
        $threshold = (int) $settings->get('free_shipping_threshold', 0);
        $free = $threshold > 0 && $price >= $threshold;
        $handling = $withTimes ? DispatchTime::days($settings) : null;

        return collect((array) $settings->get('shipping_methods', []))
            ->filter(fn (mixed $method) => is_array($method) && filled($method['label'] ?? null) && ($method['code'] ?? null) !== self::PICKUP)
            ->map(fn (array $method) => [
                'label' => (string) $method['label'],
                'price' => $free ? 0 : (int) ($method['price_gross'] ?? 0),
                'handling' => $handling,
                'transit' => $handling === null ? null : self::transitDays($method['note'] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * The days in a delivery option's note from the panel: „1–2 dni robocze” → [1, 2], „Do rąk, 1 dzień” → [1, 1].
     *
     * @return array{int, int}|null
     */
    private static function transitDays(mixed $note): ?array
    {
        if (! is_string($note) || ! preg_match('/(\d+)(?:\s*[–-]\s*(\d+))?\s*(?:dni|dzień)/u', $note, $match)) {
            return null;
        }

        $min = (int) $match[1];
        $max = filled($match[2] ?? null) ? (int) $match[2] : $min;

        return [min($min, $max), max($min, $max)];
    }
}
