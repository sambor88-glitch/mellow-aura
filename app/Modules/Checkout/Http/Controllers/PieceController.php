<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Localization\Support\Locales;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * The page of one bought piece, behind the QR code on its certificate and on the card in the parcel (MA-234).
 * Whoever holds the card may share the address, so the page says nothing about the buyer: no name, address,
 * e-mail, order number or amount. It speaks the language of the order and stays out of Google.
 */
class PieceController extends Controller
{
    public function __invoke(string $token): Response
    {
        $item = OrderItem::query()->where('page_token', $token)->with(['order', 'media', 'variant.product.category', 'variant.product.media'])->first();

        // An unpaid order, a voucher or the gift wrapping has no piece to tell about.
        abort_if($item === null || $item->order->payment_status !== PaymentStatus::Paid || ! $item->getsCertificate(), 404);

        $locale = $item->order->locale ?? Locales::default();
        if (Locales::isEnabled($locale)) {
            App::setLocale($locale);
        }

        return response()->view('checkout::piece', ['item' => $item])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }
}
