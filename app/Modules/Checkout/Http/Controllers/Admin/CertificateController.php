<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\IssueCertificates;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\CertificatePdf;
use Illuminate\Http\Response;

/**
 * The certificates of a paid order, one A6 card per handmade piece, to print and put into the parcel.
 * Opening the file numbers the pieces the first time; later the same numbers come back.
 */
class CertificateController extends Controller
{
    public function __invoke(Order $order, IssueCertificates $issueCertificates, CertificatePdf $pdf): Response
    {
        abort_unless($order->payment_status === PaymentStatus::Paid, 404);

        $certificates = $issueCertificates($order);

        abort_if($certificates->isEmpty(), 404);

        return response($pdf->render($certificates, $order->locale ?? 'pl'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.CertificatePdf::filename($order->number, $order->locale ?? 'pl').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
