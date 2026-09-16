<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Actions\SimulatePayment;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Http\Requests\PlaceOrderRequest;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Content\Support\LegalDocument;
use App\Modules\Settings\Settings;
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

        $methods = $shipping->all()->map(fn (array $method) => [...$method, 'cost' => $shipping->cost($method['code'], $subtotal)]);
        $selectedShipping = $methods->has(old('shipping_method')) ? old('shipping_method') : $methods->keys()->first();
        $selectedPayment = PaymentMethod::tryFrom((string) old('payment_method', $settings->get('default_payment_method')))->value ?? PaymentMethod::Blik->value;

        return response()->view('checkout::checkout', [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'methods' => $methods,
            'shippingGross' => $methods->get($selectedShipping)['cost'] ?? 0,
            'selectedShipping' => $selectedShipping,
            'selectedPayment' => $selectedPayment,
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
                'prices' => $methods->map(fn (array $method) => $method['price_gross']),
            ],
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    public function store(PlaceOrderRequest $request, Cart $cart, ShippingMethods $shipping, PlaceOrder $placeOrder, SimulatePayment $simulatePayment): RedirectResponse
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return to_route('checkout.index');
        }

        $data = $request->validated();
        $subtotal = $this->subtotal($lines);
        $shippingGross = $shipping->cost($data['shipping_method'], $subtotal);

        // The customer pays the sum the screen showed. If a price or stock changed meanwhile, show the new sum first.
        if ((int) $data['expected_total'] !== $subtotal + $shippingGross) {
            return back()->withInput()->with('checkout_notice', 'W koszyku coś się zmieniło — sprawdź sumę i zapłać jeszcze raz');
        }

        // The version on the site at the moment of ordering is the one the customer accepted.
        $order = $placeOrder($lines, [...$data, 'terms_version' => LegalDocument::terms()->versionLabel()], $shippingGross);

        // A failed payment never empties the cart.
        if (! $simulatePayment($order, $data['blik_code'] ?? null)) {
            return back()->withInput()->withErrors(['blik_code' => 'Bank odrzucił kod. Spróbuj jeszcze raz albo zapłać przelewem.']);
        }

        $cart->clear();
        $request->session()->put('checkout.order', $order->number);

        return to_route('checkout.confirmation');
    }

    public function confirmation(Request $request): Response|RedirectResponse
    {
        $number = $request->session()->get('checkout.order');
        $order = $number ? Order::query()->where('number', $number)->first() : null;

        if ($order === null) {
            return to_route('shop.index');
        }

        return response()->view('checkout::confirmation', [
            'order' => $order,
            'shippingLabel' => app(ShippingMethods::class)->all()->get($order->shipping_method)['label'] ?? null,
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    /**
     * @param  Collection<string, CartLine>  $lines
     */
    private function subtotal($lines): int
    {
        return (int) $lines->sum(fn (CartLine $line) => $line->total());
    }
}
