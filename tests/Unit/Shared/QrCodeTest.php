<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\QrCode;
use chillerlan\QRCode\QRCode as Decoder;
use chillerlan\QRCode\QROptions;
use PHPUnit\Framework\TestCase;

/**
 * The SVG we draw has to scan: the test paints our own path, rectangle by rectangle, onto a white bitmap and
 * hands it to a QR reader, the way a phone camera sees the printed card (MA-234).
 */
class QrCodeTest extends TestCase
{
    public function test_the_drawn_code_reads_back_as_the_address(): void
    {
        foreach (['https://mellow-aura.com/k/abcdefghjkmn', 'https://mellowaura-dev.on-forge.com/k/23456789wxyz'] as $address) {
            $this->assertSame($address, (string) (new Decoder(new QROptions(['readerUseImagickIfAvailable' => false])))->readFromBlob($this->paint(QrCode::svg($address))));
        }
    }

    public function test_the_code_keeps_a_quiet_zone_and_the_colour_of_ink(): void
    {
        $svg = QrCode::svg('https://mellow-aura.com/k/abcdefghjkmn');

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 37 37"', $svg);
        $this->assertStringContainsString('fill="#2F2620"', $svg);
        // The first dark module, the corner of a finder pattern, starts four modules in.
        $this->assertMatchesRegularExpression('/d="M4 4h7v1h-7z/', $svg);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', QrCode::dataUri('x'));
    }

    /** Our path is only „Mx yhNv1h-Nz” runs, so a bitmap of it needs no SVG renderer. */
    private function paint(string $svg, int $scale = 8): string
    {
        preg_match('/viewBox="0 0 (\d+) \d+"/', $svg, $size);
        $image = imagecreatetruecolor((int) $size[1] * $scale, (int) $size[1] * $scale);
        imagefill($image, 0, 0, imagecolorallocate($image, 243, 237, 228));
        $ink = imagecolorallocate($image, 47, 38, 32);

        preg_match_all('/M(\d+) (\d+)h(\d+)v1h-\d+z/', $svg, $runs, PREG_SET_ORDER);
        $this->assertNotEmpty($runs);
        foreach ($runs as [, $x, $y, $width]) {
            imagefilledrectangle($image, $x * $scale, $y * $scale, ($x + $width) * $scale - 1, ($y + 1) * $scale - 1, $ink);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
