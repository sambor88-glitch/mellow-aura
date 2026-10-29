<?php

namespace App\Modules\Catalog\Enums;

/**
 * What the variants of a product differ in, picked in the panel: the card in the shop counts them in these words —
 * „3 rozmiary”, „3 kwoty”, „3 kolory” — and anything else (sets, lettering, seats) as „3 warianty”.
 */
enum VariantKind: string
{
    case Sizes = 'sizes';
    case Amounts = 'amounts';
    case Colors = 'colors';
    case Options = 'options';

    /**
     * „3 rozmiary”, „5 kwot” — under the product card.
     */
    public function count(int $count): string
    {
        // Polish has two plural forms here: 2–4 (and 22–24…) rozmiary, 5+ rozmiarów.
        $few = in_array($count % 10, [2, 3, 4], true) && ! in_array($count % 100, [12, 13, 14], true);

        return __('catalog::enums.variant_kind.'.$this->value.($few ? '_few' : '_many'), ['count' => $count]);
    }

    /**
     * „Wybierz rozmiar” — the card's button that leads to the product page instead of guessing a size.
     */
    public function choose(): string
    {
        return __('catalog::enums.variant_kind_choose.'.$this->value);
    }

    /**
     * The choice in the panel.
     */
    public function option(): string
    {
        return match ($this) {
            self::Sizes => 'Rozmiarem — „3 rozmiary”',
            self::Amounts => 'Kwotą — „3 kwoty”',
            self::Colors => 'Kolorem — „3 kolory”',
            self::Options => 'Czymś innym — „3 warianty”',
        };
    }
}
