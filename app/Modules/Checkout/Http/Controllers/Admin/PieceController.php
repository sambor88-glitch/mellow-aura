<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Checkout\Support\PieceCardPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * What only the studio knows about a piece — the firing date, the glaze, the plant — and the card with the QR code
 * to its page, printed for the parcel (MA-234). Both only for a piece of a paid order, like its certificate.
 */
class PieceController extends Controller
{
    public function update(Request $request, Order $order, OrderItem $item): RedirectResponse
    {
        $this->ensurePiece($order, $item);

        // Each item has its own form on the order page, so its errors go into its own bag.
        $validator = Validator::make($request->all(), [
            'fired_on' => ['nullable', 'date', 'before_or_equal:today'],
            'glaze' => ['nullable', 'string', 'max:120'],
            'plant' => ['nullable', 'string', 'max:120'],
        ], [
            'fired_on.date' => 'Wpisz datę, np. 12.10.2026',
            'fired_on.before_or_equal' => 'Wypał nie może być w przyszłości — sprawdź datę',
            'glaze.max' => 'Szkliwo zmieść w 120 znakach',
            'plant.max' => 'Roślinę zmieść w 120 znakach',
        ]);

        if ($validator->fails()) {
            return to_route('admin.orders.show', $order)->withErrors($validator, 'piece'.$item->id)->withInput()->withFragment('sztuka-'.$item->id);
        }

        $item->update(collect($validator->validated())->map(fn (?string $value) => filled($value) ? trim($value) : null)->all());

        return to_route('admin.orders.show', $order)
            ->with('panel_status', 'Zapisane — widać to na stronie sztuki i na certyfikacie.')
            ->withFragment('sztuka-'.$item->id);
    }

    public function card(Order $order, OrderItem $item, PieceCardPdf $pdf): Response
    {
        $this->ensurePiece($order, $item);

        return response($pdf->render($item->setRelation('order', $order)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.PieceCardPdf::filename($item).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ensurePiece(Order $order, OrderItem $item): void
    {
        abort_unless($item->order_id === $order->id && $order->payment_status === PaymentStatus::Paid && $item->getsCertificate(), 404);
    }
}
