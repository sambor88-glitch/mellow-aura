<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\SettlePayment;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Http\Requests\RetryBlikRequest;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The BLIK page (/zamowienie/blik, MA-101): after the code is taken the customer confirms the payment in the bank's
 * app, and this page counts the two minutes down. It asks the shop every few seconds whether the money is in —
 * the gateway's notification decides, never the browser. When the time is up, a new code goes to the same order,
 * and the cart waits untouched the whole time.
 */
class BlikController extends Controller
{
    /** How long a BLIK code lives: the clock on the page, and the wait before a new code may be charged. */
    public const SECONDS = 120;

    private const ROBOTS = 'noindex, nofollow';

    public static function startClock(Request $request): void
    {
        $request->session()->put('checkout.blik_until', now()->addSeconds(self::SECONDS)->getTimestamp());
    }

    public function show(Request $request, SettlePayment $settle, PaymentGateway $gateway): Response|RedirectResponse
    {
        $order = $this->order($request);

        if ($order === null) {
            return to_route('shop.index');
        }

        $order = $settle($order);

        if (! $this->waitsForBlik($order, $gateway)) {
            return to_route('checkout.confirmation');
        }

        return response()->view('checkout::blik', [
            'order' => $order,
            // A refused code has no clock: the field for a new one is there straight away.
            'secondsLeft' => $order->payment_status === PaymentStatus::Pending ? $this->secondsLeft($request) : 0,
            // A workshop booking goes back to its form, and a custom order's payment to its link, not to the cart,
            // for another way to pay.
            'booking' => $booking = $order->workshopBooking,
            'customOrderPayment' => $payment = $order->customOrderPayment,
            'otherUrl' => match (true) {
                $booking !== null => $booking->workshop_date_id ? route('workshops.booking.create', $booking->workshop_date_id) : route('workshops.index'),
                $payment !== null => $payment->paymentLink->url(),
                default => route('checkout.index'),
            },
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    /**
     * What the page asks every few seconds while the clock runs.
     */
    public function status(Request $request, SettlePayment $settle): JsonResponse
    {
        $order = $this->order($request);
        $paid = $order !== null && $settle($order)->payment_status === PaymentStatus::Paid;

        return response()->json(['paid' => $paid, 'url' => $paid ? route('checkout.confirmation') : null])
            ->header('X-Robots-Tag', self::ROBOTS);
    }

    public function retry(RetryBlikRequest $request, SettlePayment $settle, PaymentGateway $gateway): RedirectResponse
    {
        $order = $this->order($request);

        if ($order === null) {
            return to_route('shop.index');
        }

        // The first code may have gone through meanwhile; then no second one is charged.
        $order = $settle($order);

        if (! $this->waitsForBlik($order, $gateway)) {
            return to_route('checkout.confirmation');
        }

        // While the first code can still be confirmed, a second one could take the money twice.
        if ($order->payment_status === PaymentStatus::Pending && $this->secondsLeft($request) > 0) {
            return to_route('checkout.blik');
        }

        $payment = $gateway->chargeBlik($order, $request->validated('blik_code'));

        if ($payment->error !== null) {
            return to_route('checkout.blik')->withErrors(['blik_code' => $payment->error]);
        }

        if ($payment->awaitsBank) {
            self::startClock($request);

            return to_route('checkout.blik');
        }

        return to_route('checkout.confirmation');
    }

    private function order(Request $request): ?Order
    {
        $number = $request->session()->get('checkout.order');

        return $number ? Order::query()->where('number', $number)->first() : null;
    }

    /** An unpaid BLIK order whose code is typed in the shop; everything else has the confirmation page. */
    private function waitsForBlik(Order $order, PaymentGateway $gateway): bool
    {
        return $order->payment_method === PaymentMethod::Blik
            && $order->payment_status !== PaymentStatus::Paid
            && $gateway->takesBlikCode();
    }

    private function secondsLeft(Request $request): int
    {
        return max(0, (int) $request->session()->get('checkout.blik_until', 0) - now()->getTimestamp());
    }
}
