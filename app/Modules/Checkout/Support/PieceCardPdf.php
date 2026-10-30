<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Pdf;
use App\Modules\Shared\Support\QrCode;

/**
 * The card that goes into the parcel with one piece (MA-234): an A6 with a QR code to the page of that piece and
 * its address written out under it, for a phone that won't scan. Same paper, typefaces and brand line as the
 * certificate; nothing about the buyer, since the card may end up in someone else's hands with the gift.
 */
class PieceCardPdf
{
    public function __construct(private Settings $settings) {}

    public function render(OrderItem $item): string
    {
        return Pdf::render($this->html($item), 'portrait', 'a6');
    }

    public function html(OrderItem $item): string
    {
        return Locales::within($item->order->locale ?? Locales::default(), fn () => view('checkout::pdf.piece-card', [
            'work' => collect([$item->pieceName(), $item->variant_label])->filter()->join(' · '),
            'qr' => QrCode::dataUri($item->pageUrl()),
            'address' => preg_replace('#^https?://#', '', $item->pageUrl()),
            'brandNote' => $this->settings->get(CertificatePdf::setting('brand_note', Locales::current())) ?: __('checkout::certificate.brand_note'),
            'fonts' => Pdf::fonts(),
        ])->render());
    }

    public static function filename(OrderItem $item): string
    {
        return Locales::within($item->order->locale ?? Locales::default(), fn () => __('checkout::piece.card_filename')).'-'.$item->order->number.'-'.$item->id.'.pdf';
    }
}
