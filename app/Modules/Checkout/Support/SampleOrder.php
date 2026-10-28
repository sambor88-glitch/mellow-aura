<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Localization\Support\Locales;

/**
 * A made-up order, never saved, for previewing the order mails in the panel („Maile”, MA-239).
 */
class SampleOrder
{
    public static function make(string $locale): Order
    {
        $currency = Locales::currency($locale);
        $price = $currency === 'PLN' ? 18_900 : 4_590;

        $item = Locales::within($locale, fn () => (new OrderItem)->forceFill([
            'product_name' => __('checkout::mail.sample.product'),
            'variant_label' => __('checkout::mail.sample.variant'),
            'quantity' => 1,
            'missing_quantity' => 0,
            'unit_price_gross' => $price,
            'product_variant_id' => 1,
        ]));

        return (new Order)->forceFill([
            'number' => 'MA-'.now()->format('Y').'-1001',
            'locale' => $locale,
            'currency' => $currency,
            'name' => 'Anna Kowalska',
            'email' => 'anna@example.com',
            'phone' => '600 100 200',
            'shipping_method' => 'parcel_locker',
            'locker_code' => 'KRA01M',
            'shipping_gross' => 0,
            'total_gross' => $price,
            'payment_method' => PaymentMethod::Blik,
            'tracking_number' => '520000011395200025754311',
            'created_at' => now(),
        ])->setRelation('items', collect([$item]));
    }
}
