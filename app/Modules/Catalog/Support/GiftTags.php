<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Settings\Settings;
use Illuminate\Support\Str;

/**
 * Who a gift is for and the occasions, as Kasia lists them in the panel (Prezenty → „Szukam prezentu”, MA-352).
 * Each list is a setting of {key, label} rows in her order; a product keeps the keys in `recipients` and `occasions`.
 * A key never changes, so a renamed label keeps its products; a removed one simply stops showing anywhere.
 */
class GiftTags
{
    public const RECIPIENTS = 'gift_recipients';

    public const OCCASIONS = 'gift_occasions';

    public const MAX_LABEL = 40;

    public const MAX_TAGS = 12;

    /**
     * @return array<string, string> key => label
     */
    public static function recipients(Settings $settings): array
    {
        return self::from($settings, self::RECIPIENTS);
    }

    /**
     * @return array<string, string> key => label
     */
    public static function occasions(Settings $settings): array
    {
        return self::from($settings, self::OCCASIONS);
    }

    /**
     * @return array<string, string> key => label, in the panel's order
     */
    public static function from(Settings $settings, string $list): array
    {
        return collect((array) $settings->get($list, []))
            ->filter(fn (mixed $tag) => is_array($tag) && filled($tag['key'] ?? null) && filled($tag['label'] ?? null))
            ->mapWithKeys(fn (array $tag) => [(string) $tag['key'] => (string) $tag['label']])
            ->all();
    }

    /**
     * A key for a new label: the label in ASCII with underscores, numbered when taken — „Dla babci” → dla_babci.
     * It is never only digits, so it stays a string key in PHP arrays.
     *
     * @param  list<string>  $taken
     */
    public static function keyFor(string $label, array $taken): string
    {
        $slug = Str::limit(Str::slug($label, '_'), 36, '');
        $base = match (true) {
            $slug === '' => 'tag',
            ctype_digit($slug) => 'tag_'.$slug,
            default => $slug,
        };

        $key = $base;
        for ($number = 2; in_array($key, $taken, true); $number++) {
            $key = $base.'_'.$number;
        }

        return $key;
    }
}
