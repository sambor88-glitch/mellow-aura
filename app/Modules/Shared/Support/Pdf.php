<?php

namespace App\Modules\Shared\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * A4 PDFs in the site's typefaces, rendered in PHP so the server needs no browser. A template gets the fonts
 * directory as $fonts and loads the faces with @include('shared::pdf.fonts'). dompdf knows no flexbox or grid,
 * so columns are tables.
 */
class Pdf
{
    public static function fonts(): string
    {
        return resource_path('fonts');
    }

    public static function render(string $html, string $orientation = 'portrait'): string
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

        $dompdf->setPaper('a4', $orientation);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
