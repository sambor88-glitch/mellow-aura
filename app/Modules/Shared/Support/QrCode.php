<?php

namespace App\Modules\Shared\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode as Encoder;
use chillerlan\QRCode\QROptions;

/**
 * A QR code as SVG, drawn on the server, for paper: the certificate and the card in the parcel (MA-234).
 * chillerlan/php-qrcode only encodes; the drawing is ours, one rectangle per run of dark modules in a row,
 * so dompdf gets a short path with no seams between neighbouring squares. Error correction M survives
 * a fold or a coffee stain on the card; the quiet zone of four modules is what phone cameras expect.
 */
class QrCode
{
    public const QUIET_ZONE = 4;

    public static function svg(string $data, string $color = '#2F2620'): string
    {
        $matrix = (new Encoder(new QROptions(['eccLevel' => EccLevel::M, 'addQuietzone' => false])))->addByteSegment($data)->getQRMatrix()->getMatrix(true);
        $size = count($matrix) + 2 * self::QUIET_ZONE;
        $path = '';

        foreach ($matrix as $y => $row) {
            $x = 0;
            $width = count($row);
            while ($x < $width) {
                if (! $row[$x]) {
                    $x++;

                    continue;
                }
                $start = $x;
                while ($x < $width && $row[$x]) {
                    $x++;
                }
                $path .= 'M'.($start + self::QUIET_ZONE).' '.($y + self::QUIET_ZONE).'h'.($x - $start).'v1h-'.($x - $start).'z';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" width="'.$size.'" height="'.$size.'" shape-rendering="crispEdges">'
            .'<path fill="'.e($color).'" d="'.$path.'"/></svg>';
    }

    /** For an <img> in a PDF: dompdf reads SVG from a data URI and draws it as vectors. */
    public static function dataUri(string $data, string $color = '#2F2620'): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($data, $color));
    }
}
