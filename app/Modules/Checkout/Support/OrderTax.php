<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Catalog\Models\ExchangeRate;
use App\Modules\Settings\Settings;

/**
 * The tax facts an order keeps as they were at the moment it was placed (MA-129): the VAT rate and its legal basis
 * from the panel's settings, the delivery country and the NBP euro rate.
 */
class OrderTax
{
    /**
     * Until shipping zones arrive (MA-128) every way of delivery stays in Poland: Polish postal codes and InPost.
     * Vouchers sent by e-mail are for workshops in Kraków.
     */
    public const DOMESTIC = 'PL';

    public function __construct(private Settings $settings) {}

    /** The rate as a decimal string ("0.00", "23.00"), never a float. */
    public function vatRate(): string
    {
        return number_format((float) $this->settings->get('vat_rate', 0), 2, '.', '');
    }

    /** The legal basis for a zero rate; nothing when VAT is charged. */
    public function vatExemption(): ?string
    {
        return (float) $this->vatRate() === 0.0 ? $this->settings->get('vat_exemption') : null;
    }

    /**
     * The NBP rate the shop works with right now, saved on the order so its value in euro never moves with the rate.
     *
     * @return array{eur_rate: ?string, eur_rate_on: ?string}
     */
    public function euroRate(): array
    {
        $rate = ExchangeRate::for('EUR');

        return ['eur_rate' => $rate?->rate, 'eur_rate_on' => $rate?->effective_on?->toDateString()];
    }
}
