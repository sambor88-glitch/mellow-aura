<?php

namespace App\Modules\Gifts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherPdf;
use Illuminate\Http\Response;

/**
 * A voucher's PDF: for the buyer through a signed link on the confirmation page, and for Kasia in the panel.
 */
class VoucherPdfController extends Controller
{
    public function __invoke(Voucher $voucher, VoucherPdf $pdf): Response
    {
        return response($pdf->render($voucher), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.VoucherPdf::filename($voucher).'"',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
