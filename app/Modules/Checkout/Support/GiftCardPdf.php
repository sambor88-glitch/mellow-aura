<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Checkout\Models\Order;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Pdf;

/**
 * The buyer's few words to the person who gets the gift (MA-342), on an A6 card for the parcel: same paper, typefaces
 * and brand line as the certificate and the piece card. Only the words — no name, price or order number, since the
 * card is read by the person who got the gift.
 */
class GiftCardPdf
{
    public function __construct(private Settings $settings) {}

    public function render(Order $order): string
    {
        return Pdf::render($this->html($order), 'portrait', 'a6');
    }

    public function html(Order $order): string
    {
        return Locales::within($order->locale ?? Locales::default(), fn () => view('checkout::pdf.gift-card', [
            'message' => (string) $order->gift_message,
            'brandNote' => $this->settings->get(CertificatePdf::setting('brand_note', Locales::current())) ?: __('checkout::certificate.brand_note'),
            'fonts' => Pdf::fonts(),
        ])->render());
    }

    public static function filename(Order $order): string
    {
        return Locales::within($order->locale ?? Locales::default(), fn () => __('checkout::gift-card.filename')).'-'.$order->number.'.pdf';
    }
}
