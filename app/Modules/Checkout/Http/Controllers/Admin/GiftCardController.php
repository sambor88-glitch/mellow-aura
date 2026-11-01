<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\GiftCardPdf;
use Illuminate\Http\Response;

/**
 * The buyer's few words to the person who gets the gift, on an A6 card to print and put into the parcel (MA-342).
 */
class GiftCardController extends Controller
{
    public function __invoke(Order $order, GiftCardPdf $pdf): Response
    {
        abort_if(blank($order->gift_message), 404);

        return response($pdf->render($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.GiftCardPdf::filename($order).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
