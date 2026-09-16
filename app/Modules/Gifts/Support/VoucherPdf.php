<?php

namespace App\Modules\Gifts\Support;

use App\Modules\Gifts\Models\Voucher;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Pdf;

/**
 * The voucher as an A4 landscape page, laid out like "Voucher warsztatowy.dc.html" in the site's typefaces.
 */
class VoucherPdf
{
    public function __construct(private Settings $settings) {}

    public function render(Voucher $voucher): string
    {
        return Pdf::render(view('gifts::pdf.voucher', [
            'voucher' => $voucher->loadMissing('orderItem'),
            'fonts' => Pdf::fonts(),
            'howToUse' => $this->settings->get('text_voucher_how_to_use'),
            'contact' => array_values(array_filter([
                $this->settings->get('contact_phone'),
                $this->settings->get('contact_email'),
                parse_url((string) config('app.url'), PHP_URL_HOST),
                ($handle = $this->settings->get('instagram_handle')) ? '@'.ltrim($handle, '@') : null,
            ])),
        ])->render(), 'landscape');
    }

    public static function filename(Voucher $voucher): string
    {
        return 'voucher-'.$voucher->code.'.pdf';
    }
}
