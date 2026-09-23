<?php

namespace App\Modules\Shared\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * PDFs in the site's typefaces, A4 unless a document asks for another paper size, rendered in PHP so the server
 * needs no browser. A template gets the fonts directory as $fonts and loads the faces with the Blade include
 * of shared::pdf.fonts. dompdf knows no flexbox or grid, so columns are tables.
 */
class Pdf
{
    public static function fonts(): string
    {
        return resource_path('fonts');
    }

    public static function render(string $html, string $orientation = 'portrait', string $paper = 'a4'): string
    {
        // dompdf keeps the measurements of each typeface here after the first document.
        File::ensureDirectoryExists($fontCache = storage_path('app/fonts'));

        $dompdf = new Dompdf(new Options([
            'chroot' => [self::fonts()],
            'fontDir' => $fontCache,
            'fontCache' => $fontCache,
            'isRemoteEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'Instrument Sans',
        ]));

        $dompdf->setPaper($paper, $orientation);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
