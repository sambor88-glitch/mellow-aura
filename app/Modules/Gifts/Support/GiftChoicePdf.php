<?php

namespace App\Modules\Gifts\Support;

use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Pdf;
use App\Modules\Shared\Support\QrCode;

/**
 * The gift choice card as an A4 landscape page (MA-235), on the voucher's paper and in its typefaces: the code, a QR
 * code to the page where she chooses, the address to type in for a phone that won't scan, and the last day.
 * Nothing about the price, so the buyer can hand it over as it is.
 */
class GiftChoicePdf
{
    /** The shop's address as printed, whatever server renders the card. */
    private const WEBSITE = 'www.mellow-aura.com';

    public function __construct(private Settings $settings) {}

    public function render(GiftChoice $choice): string
    {
        return Pdf::render($this->html($choice), 'landscape');
    }

    public function html(GiftChoice $choice): string
    {
        return view('gifts::pdf.gift-choice', [
            'choice' => $choice,
            'fonts' => Pdf::fonts(),
            'qr' => QrCode::dataUri($choice->chooseUrl()),
            'address' => self::WEBSITE.'/wybierz-kubek',
            'contact' => array_values(array_filter([
                $this->settings->get('contact_email'),
                self::WEBSITE,
                ($handle = $this->settings->get('instagram_handle')) ? '@'.ltrim($handle, '@') : null,
            ])),
        ])->render();
    }

    public static function filename(GiftChoice $choice): string
    {
        return 'karta-wyboru-'.$choice->code.'.pdf';
    }
}
