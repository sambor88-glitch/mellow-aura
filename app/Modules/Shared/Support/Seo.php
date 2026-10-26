<?php

namespace App\Modules\Shared\Support;

/**
 * Titles up to 60 characters, descriptions up to 155, cut at a word boundary.
 */
class Seo
{
    public const BRAND = 'MellowAura';

    private const TITLE_MAX = 60;

    private const DESCRIPTION_MAX = 155;

    /**
     * The first variant that fits: name, suffix and brand; name and suffix; name and brand; the name.
     */
    public static function title(string $name, string $suffix = ''): string
    {
        $candidates = [$name.$suffix.' | '.self::BRAND, $name.$suffix, $name.' | '.self::BRAND, $name];

        foreach ($candidates as $candidate) {
            if (mb_strlen($candidate) <= self::TITLE_MAX) {
                return $candidate;
            }
        }

        return mb_substr($name, 0, self::TITLE_MAX);
    }

    public static function description(?string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= self::DESCRIPTION_MAX) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::DESCRIPTION_MAX);
        $lastSpace = mb_strrpos($cut, ' ');
        $cut = $lastSpace === false ? mb_substr($cut, 0, self::DESCRIPTION_MAX - 1) : mb_substr($cut, 0, $lastSpace);

        return rtrim($cut, ' ,.;:—–-').'…';
    }
}
