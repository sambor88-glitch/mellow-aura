<?php

namespace App\Modules\Catalog\Enums;

/**
 * Whether a product is meant to touch food, which the terms of sale (§4) put on the product page. „Suitable” is for
 * after the glaze has passed the lab tests for lead and cadmium and the studio is in the sanepid register.
 */
enum FoodContact: string
{
    case Suitable = 'suitable';
    case NotSuitable = 'not_suitable';

    /**
     * What the product page and the certificate say.
     */
    public function label(): string
    {
        return match ($this) {
            self::Suitable => 'Do kontaktu z żywnością',
            self::NotSuitable => 'Nie do kontaktu z żywnością',
        };
    }

    /**
     * The choice in the panel.
     */
    public function option(): string
    {
        return match ($this) {
            self::Suitable => 'Tak — do jedzenia i picia',
            self::NotSuitable => 'Nie — tylko dekoracja',
        };
    }
}
