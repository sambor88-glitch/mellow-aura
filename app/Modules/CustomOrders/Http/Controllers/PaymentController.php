<?php

namespace App\Modules\CustomOrders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\SettlePayment;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Http\Controllers\BlikController;
use App\Modules\Checkout\Models\Order;
use App\Modules\Content\Support\LegalDocument;
use App\Modules\CustomOrders\Actions\PlacePaymentOrder;
use App\Modules\CustomOrders\Enums\PaymentLinkStatus;
use App\Modules\CustomOrders\Http\Requests\PayLinkRequest;
use App\Modules\CustomOrders\Models\PaymentLink;
use App\Modules\Payments\Contracts\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The page a payment link opens (/zamowienia-indywidualne/platnosc/{token}, MA-144). Each try to pay is an order in
 * Checkout with one item and nothing to deliver, paid the way the shop is paid: the same gateway, the same BLIK page
 * and the same notification from Przelewy24. The payment counts towards the idea in ConfirmPayment, from
 * MarkOrderPaid. Search engines never index these pages.
 */
class PaymentController extends Controller
{
    private const ROBOTS = 'noindex, nofollow';

    /**
     * Paid online, in złoty: BLIK, card and Przelewy24. A bank transfer would need Kasia to match it by hand.
     *
     * @return list<PaymentMethod>
     */
    public static function paymentMethods(): array
    {
        return array_values(array_filter(PaymentMethod::for('PLN'), fn (PaymentMethod $method) => $method !== PaymentMethod::BankTransfer));
    }

    public function show(PaymentLink $link, PaymentGateway $gateway): Response
    {
        $methods = collect(self::paymentMethods());
        $chosen = PaymentMethod::tryFrom((string) old('payment_method'));

        return response()->view('custom-orders::payment', [
            'link' => $link,
            'idea' => $link->customOrderRequest,
            'state' => match (true) {
                $link->status === PaymentLinkStatus::Paid => 'paid',
                $link->isUsable() => 'open',
                default => 'stale',
            },
            'paymentMethods' => $methods,
            'selectedPayment' => ($chosen !== null && $methods->contains($chosen) ? $chosen : $methods->first())->value,
            'blikCode' => $gateway->takesBlikCode(),
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    public function store(PayLinkRequest $request, PaymentLink $link, PlacePaymentOrder $place, PaymentGateway $gateway): RedirectResponse
    {
        if (! $link->isUsable()) {
            return to_route('custom-orders.payment.show', $link->token);
        }

        $data = $request->validated();
        $order = $place($link, ['payment_method' => $data['payment_method'], 'terms_version' => LegalDocument::terms()->versionLabel()]);
        // The BLIK code goes from the form to the gateway and is never saved anywhere.
        $payment = $gateway->start($order, $data['blik_code'] ?? null);

        if ($payment->error !== null) {
            $field = $order->payment_method === PaymentMethod::Blik ? 'blik_code' : 'payment_method';

            return back()->withInput()->withErrors([$field => $payment->error]);
        }

        // The same session key as the shop's checkout, so its BLIK page and Przelewy24's return find the order.
        $request->session()->put('checkout.order', $order->number);

        if ($payment->awaitsBank) {
            BlikController::startClock($request);

            return to_route('checkout.blik');
        }

        if ($payment->redirectTo !== null) {
            return redirect()->away($payment->redirectTo);
        }

        return to_route('custom-orders.payment.confirmation');
    }

    /**
     * Where the payment ends: paid, or still waiting for Przelewy24. A failed payment goes back to the link.
     */
    public function confirmation(Request $request, SettlePayment $settle, PaymentGateway $gateway): Response|RedirectResponse
    {
        $number = $request->session()->get('checkout.order');
        $order = $number ? Order::query()->where('number', $number)->first() : null;
        $payment = $order?->customOrderPayment;

        if ($order === null || $payment === null) {
            return to_route('custom-orders.index');
        }

        $order = $settle($order);

        if ($order->payment_method === PaymentMethod::Blik && $order->payment_status === PaymentStatus::Pending
            && filled($order->payment_provider_id) && $gateway->takesBlikCode()) {
            return to_route('checkout.blik');
        }

        if ($order->payment_status === PaymentStatus::Failed) {
            $request->session()->forget('checkout.order');

            return to_route('custom-orders.payment.show', $payment->paymentLink->token)
                ->with('payment_notice', 'Bank nie przyjął płatności. Spróbuj jeszcze raz albo wybierz inny sposób.');
        }

        return response()->view('custom-orders::payment-confirmation', [
            'order' => $order,
            'idea' => $payment->paymentLink->customOrderRequest,
            'paid' => $order->payment_status === PaymentStatus::Paid,
        ])->header('X-Robots-Tag', self::ROBOTS);
    }
}
