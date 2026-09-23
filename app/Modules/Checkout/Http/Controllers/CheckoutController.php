<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Http\Requests\PlaceOrderRequest;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Content\Support\LegalDocument;
use App\Modules\Localization\Support\Locales;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Support\StripeKeys;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\AnalyticsItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * The one-screen checkout (/zamowienie). Search engines never index it, in production too.
 */
class CheckoutController extends Controller
{
    private const ROBOTS = 'noindex, nofollow';

    public function show(Cart $cart, ShippingMethods $shipping, Settings $settings): Response
    {
        $lines = $cart->lines();
        $subtotal = $this->subtotal($lines);
        // Vouchers sent as PDFs alone come by e-mail, so there is no delivery to choose or pay for.
        $needsDelivery = $cart->needsDelivery();

        $methods = $needsDelivery
            ? $shipping->all()->map(fn (array $method) => [...$method, 'cost' => $shipping->cost($method['code'], $subtotal)])
            : collect();
        $selectedShipping = match (true) {
            ! $needsDelivery => ShippingMethods::EMAIL,
            $methods->has(old('shipping_method')) => old('shipping_method'),
            default => $methods->keys()->first(),
        };
        $paymentMethods = $this->paymentMethods();
        $chosen = PaymentMethod::tryFrom((string) old('payment_method', $settings->get('default_payment_method')));
        $selectedPayment = ($chosen !== null && $paymentMethods->contains($chosen) ? $chosen : $paymentMethods->first())->value;

        return response()->view('checkout::checkout', [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'methods' => $methods,
            'needsDelivery' => $needsDelivery,
            'shippingGross' => $methods->get($selectedShipping)['cost'] ?? 0,
            'selectedShipping' => $selectedShipping,
            'paymentMethods' => $paymentMethods,
            'selectedPayment' => $selectedPayment,
            'analytics' => $lines->isEmpty() ? null : AnalyticsItem::params($subtotal, $this->analyticsItems($lines)),
            'config' => [
                'accepted' => (bool) old('accept_terms'),
                // One checkbox per line with a feature to accept, ticked again after a mistake elsewhere in the form.
                'deviations' => $lines->filter(fn (CartLine $line) => $line->deviations() !== [])
                    ->map(fn (CartLine $line, string $key) => (bool) old('accept_deviations.'.$key))
                    ->all(),
                'payment' => $selectedPayment,
                'shipping' => $selectedShipping,
                'subtotal' => $subtotal,
                'freeFrom' => $shipping->freeFrom(),
                'prices' => $methods->map(fn (array $method) => $method['price']),
                // What the pay button and the pill say, in the language of the page.
                'texts' => __('checkout::checkout.js'),
                // With keys the browser hands the code to Stripe itself; without them the shop pays itself.
                'stripeKey' => StripeKeys::publishable(),
            ],
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    public function store(PlaceOrderRequest $request, Cart $cart, ShippingMethods $shipping, PlaceOrder $placeOrder, PaymentGateway $gateway): RedirectResponse|JsonResponse
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return to_route('checkout.index');
        }

        $data = $request->validated();
        $subtotal = $this->subtotal($lines);
        $needsDelivery = $cart->needsDelivery();
        $data['shipping_method'] = $needsDelivery ? $data['shipping_method'] : ShippingMethods::EMAIL;
        $shippingGross = $needsDelivery ? $shipping->cost($data['shipping_method'], $subtotal) : 0;

        // The customer pays the sum the screen showed. If a price or stock changed meanwhile, show the new sum first.
        if ((int) $data['expected_total'] !== $subtotal + $shippingGross) {
            return back()->withInput()->with('checkout_notice', __('checkout::checkout.changed'));
        }

        // The version on the site at the moment of ordering is the one the customer accepted.
        $order = $placeOrder($lines, [...$data, 'terms_version' => LegalDocument::terms()->versionLabel()], $shippingGross);

        // The BLIK code goes from the form to the gateway and is never saved anywhere.
        $payment = $gateway->start($order, $data['blik_code'] ?? null);

        // A refused payment never empties the cart. The sentence lands on the field it is about.
        if ($payment->error !== null) {
            $field = $order->payment_method === PaymentMethod::Blik ? 'blik_code' : 'payment_method';

            return back()->withInput()->withErrors([$field => $payment->error]);
        }

        $request->session()->put('checkout.order', $order->number);
        // The cart is emptied on the confirmation page, so the purchase for Google Analytics is kept here.
        $request->session()->put('checkout.analytics', ['order' => $order->number, 'value' => $subtotal, 'items' => $this->analyticsItems($lines)]);

        // The browser finishes the payment with the gateway and only then goes to the confirmation.
        if ($payment->clientSecret !== null) {
            return response()->json(['secret' => $payment->clientSecret, 'next' => route('checkout.confirmation')]);
        }

        return to_route('checkout.confirmation');
    }

    public function confirmation(Request $request, Cart $cart, PaymentGateway $gateway, MarkOrderPaid $markOrderPaid): Response|RedirectResponse
    {
        $number = $request->session()->get('checkout.order');
        $order = $number ? Order::query()->where('number', $number)->first() : null;

        if ($order === null) {
            return to_route('shop.index');
        }

        $order = $this->settle($order, $gateway, $markOrderPaid);

        // The cart waited for this moment, so a payment the bank refused leaves it exactly as it was.
        if ($order->payment_status === PaymentStatus::Failed) {
            $request->session()->forget('checkout.order');

            return to_route('checkout.index')->with('checkout_notice', __('checkout::checkout.bank_refused'));
        }

        $cart->clear();

        $analytics = $request->session()->get('checkout.analytics');

        return response()->view('checkout::confirmation', [
            'order' => $order,
            'paid' => $order->payment_status === PaymentStatus::Paid,
            'purchase' => ($analytics['order'] ?? null) === $order->number
                ? AnalyticsItem::params((int) $analytics['value'], $analytics['items'], ['transaction_id' => $order->number, 'shipping' => $order->shipping_gross / 100])
                : null,
            'shippingLabel' => app(ShippingMethods::class)->label($order->shipping_method),
            'parcel' => $order->sendsParcel(),
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    /**
     * The ways to pay in the currency of the basket (PaymentMethod::for). The card is paid in a Stripe field on
     * this page, BLIK with the code from our own field and Przelewy24 by sending the customer to their bank.
     *
     * @return Collection<int, PaymentMethod>
     */
    private function paymentMethods(): Collection
    {
        return collect(PaymentMethod::for(Locales::currency()));
    }

    /**
     * The customer can be back in the shop before the gateway's own call reaches us, so we ask it
     * ourselves. MarkOrderPaid is safe to call twice, so the call arriving later changes nothing.
     */
    private function settle(Order $order, PaymentGateway $gateway, MarkOrderPaid $markOrderPaid): Order
    {
        if ($order->payment_status !== PaymentStatus::Pending || blank($order->payment_provider_id)) {
            return $order;
        }

        return match ($gateway->state($order)) {
            PaymentState::Succeeded => $markOrderPaid($order, $order->payment_provider_id),
            PaymentState::Failed => tap($order)->update(['payment_status' => PaymentStatus::Failed]),
            PaymentState::Pending => $order,
        };
    }

    /**
     * @param  Collection<string, CartLine>  $lines
     * @return list<array<string, string|int|float>>
     */
    private function analyticsItems(Collection $lines): array
    {
        return $lines->map(fn (CartLine $line) => $line->analyticsItem())->values()->all();
    }

    /**
     * @param  Collection<string, CartLine>  $lines
     */
    private function subtotal($lines): int
    {
        return (int) $lines->sum(fn (CartLine $line) => $line->total());
    }
}
