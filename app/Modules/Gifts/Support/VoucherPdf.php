<?php

namespace App\Modules\Gifts\Support;

use App\Modules\Gifts\Models\Voucher;
use App\Modules\Settings\Settings;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * The voucher as an A4 landscape page, laid out like "Voucher warsztatowy.dc.html" in the site's
 * typefaces, which live in resources/fonts. Rendered in PHP, so the server needs no browser.
 */
class VoucherPdf
{
    public function __construct(private Settings $settings) {}

    public function render(Voucher $voucher): string
    {
        // dompdf keeps the measurements of each typeface here after the first voucher.
        File::ensureDirectoryExists($fontCache = storage_path('app/fonts'));

        $dompdf = new Dompdf(new Options([
            'chroot' => [resource_path('fonts')],
            'fontDir' => $fontCache,
            'fontCache' => $fontCache,
            'isRemoteEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'Instrument Sans',
        ]));

        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(view('gifts::pdf.voucher', [
            'voucher' => $voucher->loadMissing('orderItem'),
            'fonts' => resource_path('fonts'),
            'howToUse' => $this->settings->get('text_voucher_how_to_use'),
            'contact' => array_values(array_filter([
                $this->settings->get('contact_phone'),
                $this->settings->get('contact_email'),
                parse_url((string) config('app.url'), PHP_URL_HOST),
                ($handle = $this->settings->get('instagram_handle')) ? '@'.ltrim($handle, '@') : null,
            ])),
        ])->render(), 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public static function filename(Voucher $voucher): string
    {
        return 'voucher-'.$voucher->code.'.pdf';
    }
}
