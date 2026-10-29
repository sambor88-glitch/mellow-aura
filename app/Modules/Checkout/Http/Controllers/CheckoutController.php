<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Actions\SettlePayment;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Http\Requests\PlaceOrderRequest;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\LockerMap;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Checkout\Support\VoucherCodes;
use App\Modules\Content\Support\LegalDocument;
use App\Modules\Gifts\Actions\RestoreVoucherBalance;
use App\Modules\Gifts\Enums\BalanceRefusal;
use App\Modules\Gifts\Exceptions\BalanceNotAccepted;
use App\Modules\Gifts\Support\VoucherBalance;
use App\Modules\Localization\Support\Locales;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\AnalyticsItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * The one-screen checkout (/zamowienie). Search engines never index it, in production too.
 */
class CheckoutController extends Controller
{
    private const ROBOTS = 'noindex, nofollow';

    public function show(Request $request, Cart $cart, ShippingMethods $shipping, Settings $settings, PaymentGateway $gateway): Response|RedirectResponse
    {
        if ($redirect = $this->paidElsewhere($request, $cart)) {
            return $redirect;
        }

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
        $shippingGross = $methods->get($selectedShipping)['cost'] ?? 0;
        // Amount vouchers typed in so far (MA-211), with what each pays towards the sum on the screen.
        $vouchers = VoucherBalance::preview($this->voucherCodes($request), $subtotal + $shippingGross);
        $paymentMethods = $this->paymentMethods();
        $chosen = PaymentMethod::tryFrom((string) old('payment_method', $settings->get('default_payment_method')));
        $selectedPayment = ($chosen !== null && $paymentMethods->contains($chosen) ? $chosen : $paymentMethods->first())->value;

        return response()->view('checkout::checkout', [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'methods' => $methods,
            'needsDelivery' => $needsDelivery,
            'shippingGross' => $shippingGross,
            'selectedShipping' => $selectedShipping,
            'acceptsVouchers' => VoucherCodes::accepted(),
            'vouchers' => $vouchers,
            'paymentMethods' => $paymentMethods,
            'selectedPayment' => $selectedPayment,
            // Without BLIK Level 0 the code is typed on Przelewy24's page, so there is no field for it here.
            'blikCode' => $gateway->takesBlikCode(),
            'analytics' => $lines->isEmpty() ? null : AnalyticsItem::params($subtotal, $this->analyticsItems($lines)),
            'config' => [
                'accepted' => (bool) old('accept_terms'),
                // One checkbox per line with a feature to accept, ticked again after a mistake elsewhere in the form.
                'deviations' => $lines->filter(fn (CartLine $line) => $line->deviations() !== [])
                    ->map(fn (CartLine $line, string $key) => (bool) old('accept_deviations.'.$key))
                    ->all(),
                'payment' => $selectedPayment,
                'blikCode' => $gateway->takesBlikCode(),
                'shipping' => $selectedShipping,
                'locker' => (string) old('locker_code', ''),
                // InPost's map of lockers, or null without a token: then the code is typed by hand.
                'lockerMap' => LockerMap::config(),
                'subtotal' => $subtotal,
                // What is left on each voucher, in the order typed: the screen splits a new sum between them.
                'vouchers' => $vouchers->map(fn (array $use) => ['code' => $use['voucher']->code, 'balance' => $use['voucher']->balance_minor])->all(),
                'freeFrom' => $shipping->freeFrom(),
                'prices' => $methods->map(fn (array $method) => $method['price']),
                // What the pay button and the pill say, in the language of the page.
                'texts' => __('checkout::checkout.js'),
                // What the server would say about the fields above the BLIK code, said before the code is typed.
                'fieldTexts' => collect([
                    'email' => 'email_required',
                    'email_format' => 'email_email',
                    'phone' => $needsDelivery ? 'phone_required_parcel' : 'phone_required',
                    'phone_format' => 'phone_regex',
                    'name' => 'name_required',
                    'street' => 'street_required',
                    'postal_code' => 'postal_code_required',
                    'postal_code_format' => 'postal_code_regex',
                    'city' => 'city_required',
                    'locker_code' => 'locker_code_required',
                    'locker_code_format' => 'locker_code',
                ])->map(fn (string $key) => __('checkout::validation.'.$key)),
            ],
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    public function store(PlaceOrderRequest $request, Cart $cart, ShippingMethods $shipping, PlaceOrder $placeOrder, PaymentGateway $gateway,
        MarkOrderPaid $markOrderPaid, RestoreVoucherBalance $restoreVouchers): RedirectResponse
    {
        if ($redirect = $this->paidElsewhere($request, $cart)) {
            return $redirect;
        }

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

        // The same goes for the vouchers: one spent elsewhere meanwhile leaves more to pay (MA-211).
        $codes = $this->voucherCodes($request);
        $vouchersGross = (int) VoucherBalance::preview($codes, $subtotal + $shippingGross)->sum('amount');

        if ((int) ($data['expected_vouchers'] ?? 0) !== $vouchersGross) {
            return back()->withInput()->with('checkout_notice', __('checkout::checkout.changed'));
        }

        $due = $subtotal + $shippingGross - $vouchersGross;

        if ($due > 0 && $data['payment_method'] === PaymentMethod::Blik->value && $gateway->takesBlikCode() && blank($data['blik_code'] ?? null)) {
            return back()->withInput()->withErrors(['blik_code' => __('checkout::validation.blik_code')]);
        }

        // A new order from the same cart gives up the last unpaid one: its voucher money is free again.
        $this->abandonPrevious($request, $restoreVouchers);

        try {
            // The version on the site at the moment of ordering is the one the customer accepted.
            $order = $placeOrder($lines, [...$data, 'terms_version' => LegalDocument::terms()->versionLabel()], $shippingGross, $codes);
        } catch (BalanceNotAccepted $refused) {
            VoucherCodes::forget($request, $refused->typedCode);

            return back()->withInput()->withErrors(['voucher_code' => VoucherCodes::refusal($refused)]);
        }

        // Another order took the voucher's money between the check above and the lock in PlaceOrder.
        if ($order->vouchers_gross !== $vouchersGross) {
            $restoreVouchers->abandoned($order);

            return back()->withInput()->with('checkout_notice', __('checkout::checkout.changed'));
        }

        $request->session()->put('checkout.order', $order->number);

        // Vouchers paid for everything: nothing goes through the gateway.
        if ($order->amountDue() === 0) {
            $markOrderPaid($order, 'voucher');
            $request->session()->put('checkout.analytics', ['order' => $order->number, 'value' => $subtotal, 'items' => $this->analyticsItems($lines)]);

            return to_route('checkout.confirmation');
        }

        // The BLIK code goes from the form to the gateway and is never saved anywhere.
        $payment = $gateway->start($order, $data['blik_code'] ?? null);

        // A refused payment never empties the cart. The sentence lands on the field it is about.
        if ($payment->error !== null) {
            $field = $order->payment_method === PaymentMethod::Blik ? 'blik_code' : 'payment_method';
            $restoreVouchers->abandoned($order);
            $request->session()->forget('checkout.order');

            return back()->withInput()->withErrors([$field => $payment->error]);
        }

        // The cart is emptied on the confirmation page, so the purchase for Google Analytics is kept here.
        $request->session()->put('checkout.analytics', ['order' => $order->number, 'value' => $subtotal, 'items' => $this->analyticsItems($lines)]);

        // The code was taken: the customer confirms in the bank's app while the BLIK page counts down.
        if ($payment->awaitsBank) {
            BlikController::startClock($request);

            return to_route('checkout.blik');
        }

        // A card, an online transfer or BLIK without Level 0 is finished on Przelewy24's page, which sends the
        // customer back to the confirmation.
        if ($payment->redirectTo !== null) {
            return redirect()->away($payment->redirectTo);
        }

        return to_route('checkout.confirmation');
    }

    public function confirmation(Request $request, Cart $cart, SettlePayment $settle, PaymentGateway $gateway, RestoreVoucherBalance $restoreVouchers): Response|RedirectResponse
    {
        $order = $this->confirmedOrder($request);

        if ($order === null) {
            return to_route('shop.index');
        }

        // The session that placed the order sees all of it. A signed link alone, from the bank's app in another
        // browser, sees the number and the payment status only (MA-219).
        $own = $order->number === $request->session()->get('checkout.order');
        $order = $settle($order);

        if ($own) {
            // A BLIK code still waiting for the bank's app has its own page with the clock.
            if ($order->payment_method === PaymentMethod::Blik && $order->payment_status === PaymentStatus::Pending
                && filled($order->payment_provider_id) && $gateway->takesBlikCode()) {
                return to_route('checkout.blik');
            }

            // A workshop booking has its own confirmation with the date and the seats, and leaves the cart alone.
            if ($order->workshopBooking()->exists()) {
                return to_route('workshops.booking.confirmation');
            }

            // So does a payment for a custom order, with what the money is for.
            if ($order->customOrderPayment()->exists()) {
                return to_route('custom-orders.payment.confirmation');
            }

            // The cart waited for this moment, so a payment the bank refused leaves it exactly as it was.
            if ($order->payment_status === PaymentStatus::Failed) {
                $restoreVouchers->abandoned($order);
                $request->session()->forget('checkout.order');

                return to_route('checkout.index')->with('checkout_notice', __('checkout::checkout.bank_refused'));
            }

            $this->clearCartOnce($request, $cart, $order);
        }

        $paid = $order->payment_status === PaymentStatus::Paid;
        $byTransfer = $order->payment_method === PaymentMethod::BankTransfer;
        $analytics = $own ? $request->session()->get('checkout.analytics') : null;

        return response()->view('checkout::confirmation', [
            'order' => $order,
            'own' => $own,
            'paid' => $paid,
            // Unpaid by bank transfer waits for the money; unpaid otherwise waits for Przelewy24's word on it.
            'byTransfer' => $byTransfer,
            // Cancelled or closed on Przelewy24's page: Przelewy24 says nothing about it, so the way back to paying
            // stays next to the wait. A BLIK code typed in the shop has its own page for that.
            'resumeUrl' => ! $paid && ! $byTransfer && ! ($order->payment_method === PaymentMethod::Blik && $gateway->takesBlikCode())
                ? URL::temporarySignedRoute('checkout.resume', now()->addDay(), ['order' => $order->number])
                : null,
            'purchase' => ($analytics['order'] ?? null) === $order->number
                ? AnalyticsItem::params((int) $analytics['value'], $analytics['items'], ['transaction_id' => $order->number, 'shipping' => $order->shipping_gross / 100])
                : null,
            'shippingLabel' => app(ShippingMethods::class)->label($order->shipping_method),
            // A pickup in the studio is never packed for the post: no parcel photo, no shipping time.
            'parcel' => $order->sendsParcel() && $order->shipping_method !== 'studio_pickup',
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    /**
     * „Użyj vouchera” (MA-211): the whole form comes here, so everything typed so far comes back filled in. The code is
     * checked and kept for the order; the screen then shows what it pays. The BLIK code is never kept, as everywhere.
     */
    public function addVoucher(Request $request): RedirectResponse
    {
        abort_unless(VoucherCodes::accepted(), 404);

        $code = trim((string) $request->input('voucher_code'));
        $typed = $request->except(['_token', 'blik_code']);
        $kept = VoucherCodes::all($request);

        try {
            $voucher = VoucherBalance::find($code);
            throw_if(in_array($voucher->code, $kept, true), new BalanceNotAccepted($code, BalanceRefusal::Twice, $voucher));
        } catch (BalanceNotAccepted $refused) {
            $message = $code === '' ? __('checkout::checkout.voucher_refused.empty') : VoucherCodes::refusal($refused);

            return to_route('checkout.index')->withInput($typed)->withErrors(['voucher_code' => $message]);
        }

        if (count($kept) >= VoucherBalance::MAX_CODES) {
            return to_route('checkout.index')->withInput($typed)
                ->withErrors(['voucher_code' => __('checkout::checkout.voucher_refused.too_many', ['count' => VoucherBalance::MAX_CODES])]);
        }

        VoucherCodes::add($request, $voucher->code);

        return to_route('checkout.index')->withInput(Arr::except($typed, 'voucher_code'))->with('voucher_added', $voucher->code);
    }

    public function removeVoucher(Request $request): RedirectResponse
    {
        VoucherCodes::forget($request, (string) $request->input('remove_voucher'));

        return to_route('checkout.index')->withInput($request->except(['_token', 'blik_code', 'voucher_code', 'remove_voucher']));
    }

    /**
     * „Dokończ płatność”: a new try at the same unpaid order, after the customer cancelled or closed Przelewy24's
     * page (MA-219). The link is signed, so it works from the bank's app in another browser too.
     */
    public function resume(Request $request, SettlePayment $settle, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $order = Order::query()->where('number', (string) $request->query('order'))->firstOrFail();
        // The first try may have gone through meanwhile; then no second one is started.
        $order = $settle($order);

        if ($order->payment_status === PaymentStatus::Paid || $order->payment_method === PaymentMethod::BankTransfer) {
            return redirect()->to($order->confirmationUrl());
        }

        $payment = $gateway->resume($order);

        if ($payment->error !== null) {
            return redirect()->to($order->confirmationUrl())->with('checkout_notice', $payment->error);
        }

        return $payment->redirectTo !== null
            ? redirect()->away($payment->redirectTo)
            : redirect()->to($order->confirmationUrl());
    }

    /**
     * The order from the signed link Przelewy24 sends the customer back with, or from this browser's session.
     * A link with a wrong or expired signature is refused, unless the session knows the order anyway.
     */
    private function confirmedOrder(Request $request): ?Order
    {
        $number = $request->session()->get('checkout.order');

        if ($request->has('order')) {
            if ($request->hasValidSignature()) {
                $number = (string) $request->query('order');
            } elseif ($request->query('order') !== $number) {
                abort(403);
            }
        }

        return $number ? Order::query()->where('number', $number)->first() : null;
    }

    /**
     * The cart empties once the order is paid, or placed for a bank transfer, and only once per order (MA-219):
     * a cancelled payment keeps it, and a second look at an old confirmation never empties a new cart.
     */
    private function clearCartOnce(Request $request, Cart $cart, Order $order): void
    {
        if ($order->payment_status !== PaymentStatus::Paid && $order->payment_method !== PaymentMethod::BankTransfer) {
            return;
        }

        if ($request->session()->get('checkout.cleared') === $order->number) {
            return;
        }

        $cart->clear();
        $request->session()->forget(VoucherCodes::SESSION_KEY);
        $request->session()->put('checkout.cleared', $order->number);
    }

    /**
     * The order in this session was paid somewhere else — in the bank's app, in another browser — while the cart
     * here still holds what it paid for. Then the checkout shows that confirmation instead of a second purchase.
     */
    private function paidElsewhere(Request $request, Cart $cart): ?RedirectResponse
    {
        $number = $request->session()->get('checkout.order');

        if ($number === null || $request->session()->get('checkout.cleared') === $number) {
            return null;
        }

        $order = Order::query()->where('number', $number)->where('payment_status', PaymentStatus::Paid)->first();

        if ($order === null || $order->workshopBooking()->exists() || $order->customOrderPayment()->exists()) {
            return null;
        }

        $this->clearCartOnce($request, $cart, $order);

        return to_route('checkout.confirmation');
    }

    /**
     * The order this session placed last, if it is still unpaid and the customer is ordering again. A bank transfer
     * counts too: whoever orders again is not going to send the first one.
     */
    private function abandonPrevious(Request $request, RestoreVoucherBalance $restoreVouchers): void
    {
        $number = $request->session()->get('checkout.order');
        $previous = $number === null ? null : Order::query()->where('number', $number)->where('payment_status', '!=', PaymentStatus::Paid)->first();

        if ($previous !== null) {
            $restoreVouchers->abandoned($previous);
        }
    }

    /**
     * @return list<string>
     */
    private function voucherCodes(Request $request): array
    {
        return VoucherCodes::accepted() ? VoucherCodes::all($request) : [];
    }

    /**
     * The ways to pay in the currency of the basket (PaymentMethod::for). BLIK takes the code from our own field;
     * the rest go through Przelewy24 (MA-167).
     *
     * @return Collection<int, PaymentMethod>
     */
    private function paymentMethods(): Collection
    {
        return collect(PaymentMethod::for(Locales::currency()));
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
