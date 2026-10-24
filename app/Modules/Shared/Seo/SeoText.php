<?php

namespace App\Modules\Shared\Seo;

/**
 * Title and description limits from the specification, as fit() and clip() in the prototype.
 */
final class SeoText
{
    public const TITLE_LIMIT = 60;

    public const DESCRIPTION_LIMIT = 155;

    /**
     * First option that fits in 60 characters. Options go from the fullest to the shortest,
     * so the brand suffix drops first: ['Kubek — ceramika handmade | MellowAura', 'Kubek — ceramika handmade', ...].
     * When none fits, the last one is cut at a word boundary.
     *
     * @param  list<string>|string  $options
     */
    public static function title(array|string $options): string
    {
        $options = array_map(self::normalize(...), (array) $options);

        foreach ($options as $option) {
            if (mb_strlen($option) <= self::TITLE_LIMIT) {
                return $option;
            }
        }

        return self::cutAtWord(end($options), self::TITLE_LIMIT);
    }

    /**
     * Up to 155 characters; a longer text is cut at a word boundary and ends with "…".
     */
    public static function description(?string $text): string
    {
        $text = self::normalize(strip_tags((string) $text));

        if (mb_strlen($text) <= self::DESCRIPTION_LIMIT) {
            return $text;
        }

        return self::cutAtWord($text, self::DESCRIPTION_LIMIT - 1).'…';
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function cutAtWord(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit + 1);
        $space = mb_strrpos($cut, ' ');

        $result = $space > 0 ? mb_substr($cut, 0, $space) : mb_substr($text, 0, $limit);

        // A dangling dash or comma reads as a mistake in the search result.
        return (string) preg_replace('/[\s,;:—–-]+$/u', '', $result);
    }
}
