<?php

namespace App\Modules\Catalog\Support;

/**
 * The cards above the shop („Zrobię dla Ciebie”) that lead to pages made to order. A card shows up once its page
 * exists in the language of the shop; the line under its name is Kasia's, from the panel (Kategorie).
 */
class ShopShortcuts
{
    /** @var array<string, string> label => route name */
    public const ROUTES = [
        'mug' => 'mug.index',
        'bundles' => 'bundles.index',
        'gift_finder' => 'gifts.index',
        'scarf' => 'content.scarf',
        'imprint' => 'content.imprint',
        'custom_orders' => 'custom-orders.index',
    ];

    /**
     * The settings key of a card's line: Polish without a suffix, other languages with theirs.
     */
    public static function noteKey(string $label, string $locale): string
    {
        return 'shop_shortcut_note_'.$label.($locale === 'pl' ? '' : '_'.$locale);
    }
}
