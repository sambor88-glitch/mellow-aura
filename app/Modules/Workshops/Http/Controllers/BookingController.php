<?php

namespace App\Modules\Workshops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\SettlePayment;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Http\Controllers\BlikController;
use App\Modules\Checkout\Models\Order;
use App\Modules\Content\Support\LegalDocument;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Settings\Settings;
use App\Modules\Workshops\Actions\PlaceBooking;
use App\Modules\Workshops\Actions\ReleaseBooking;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Http\Requests\BookWorkshopRequest;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\WorkshopTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Booking a seat on a workshop date (/warsztaty-ceramiczne-krakow/zapis/{date}). The booking is an order in Checkout
 * with one item and nothing to deliver, paid the way the shop is paid: the same gateway, the same BLIK page and the
 * same notification from Przelewy24. The seats are held while the payment goes (PlaceBooking) and become hers when it
 * is confirmed (ConfirmBooking, from MarkOrderPaid). Search engines never index these pages.
 */
class BookingController extends Controller
{
    /** The most seats one booking takes through the site; a bigger group writes to Kasia. */
    public const MAX_SEATS = 6;

    private const ROBOTS = 'noindex, nofollow';

    /**
     * Paid online only: a bank transfer would hold a seat for days. BLIK, card and Przelewy24 (MA-136).
     *
     * @return list<PaymentMethod>
     */
    public static function paymentMethods(): array
    {
        return array_values(array_filter(PaymentMethod::for('PLN'), fn (PaymentMethod $method) => $method !== PaymentMethod::BankTransfer));
    }

    public function create(WorkshopDate $date, WorkshopTypes $types, Settings $settings, PaymentGateway $gateway): Response|RedirectResponse
    {
        $workshop = $this->workshop($date, $types);

        if ($workshop === null) {
            return to_route('workshops.index');
        }

        $methods = collect(self::paymentMethods());
        $chosen = PaymentMethod::tryFrom((string) old('payment_method'));

        return response()->view('workshops::booking', [
            'date' => $date,
            'workshop' => $workshop,
            'seatsLeft' => $seatsLeft = $date->seatsLeft(),
            'maxSeats' => min($seatsLeft, self::MAX_SEATS),
            'paymentMethods' => $methods,
            'selectedPayment' => ($chosen !== null && $methods->contains($chosen) ? $chosen : $methods->first())->value,
            'blikCode' => $gateway->takesBlikCode(),
            'location' => $settings->get('location_description'),
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    public function store(BookWorkshopRequest $request, WorkshopDate $date, WorkshopTypes $types, PlaceBooking $placeBooking, ReleaseBooking $release, PaymentGateway $gateway): RedirectResponse
    {
        $workshop = $this->workshop($date, $types);

        if ($workshop === null) {
            return to_route('workshops.index');
        }

        $data = $request->validated();
        $seats = (int) $data['seats'];

        // She pays the sum the screen showed. If Kasia changed the price meanwhile, show the new one first.
        if ((int) $data['expected_total'] !== $workshop['price_gross'] * $seats) {
            return back()->withInput()->with('booking_notice', 'Cena warsztatu właśnie się zmieniła — sprawdź nową kwotę i zapisz się jeszcze raz.');
        }

        $booking = $placeBooking($date, $workshop, $seats, [...$data, 'terms_version' => LegalDocument::terms()->versionLabel()]);

        if ($booking === null) {
            $left = $date->seatsLeft();

            return back()->withInput()->withErrors(['seats' => $left === 0
                ? 'Ktoś zajął ostatnie miejsce chwilę przed Tobą — wybierz inny termin.'
                : 'Zostało już tylko '.$date->seatsLeftLabel().' — zmniejsz liczbę miejsc.']);
        }

        $order = $booking->order;
        // The BLIK code goes from the form to the gateway and is never saved anywhere.
        $payment = $gateway->start($order, $data['blik_code'] ?? null);

        // A refused payment gives the seats back at once; the form keeps what she typed.
        if ($payment->error !== null) {
            $release($order);
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

        return to_route('workshops.booking.confirmation');
    }

    /**
     * Where the booking ends: paid with her seats, paid too late for a full date, or still waiting for Przelewy24.
     * A failed payment frees the seats and goes back to the form.
     */
    public function confirmation(Request $request, SettlePayment $settle, ReleaseBooking $release, PaymentGateway $gateway, Settings $settings): Response|RedirectResponse
    {
        $number = $request->session()->get('checkout.order');
        $order = $number ? Order::query()->where('number', $number)->first() : null;
        $booking = $order?->workshopBooking;

        // No booking in the session, or Kasia cancelled it and deleted its date meanwhile.
        if ($order === null || $booking === null || $booking->workshop_date_id === null) {
            return to_route('workshops.index');
        }

        $order = $settle($order);

        if ($order->payment_method === PaymentMethod::Blik && $order->payment_status === PaymentStatus::Pending
            && filled($order->payment_provider_id) && $gateway->takesBlikCode()) {
            return to_route('checkout.blik');
        }

        if ($order->payment_status === PaymentStatus::Failed) {
            $release($order);
            $request->session()->forget('checkout.order');

            return to_route('workshops.booking.create', $booking->workshop_date_id)
                ->with('booking_notice', 'Bank nie przyjął płatności, więc miejsce wróciło do puli. Spróbuj jeszcze raz albo wybierz inny sposób płatności.');
        }

        /** @var WorkshopBooking $booking */
        $booking = $booking->fresh(['workshopDate']);

        return response()->view('workshops::booking-confirmation', [
            'order' => $order,
            'booking' => $booking,
            'date' => $booking->workshopDate,
            'paid' => $order->payment_status === PaymentStatus::Paid,
            'overbooked' => $booking->status === BookingStatus::Overbooked,
            'location' => $settings->get('location_description'),
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    /**
     * The date's row of the price list, or null for a date that has passed or whose workshop left the price list.
     *
     * @return ?array{name: string, price_gross: int, unit_label: ?string, duration_label: ?string}
     */
    private function workshop(WorkshopDate $date, WorkshopTypes $types): ?array
    {
        if ($date->starts_at->isPast()) {
            return null;
        }

        return $types->all()->first(fn (array $workshop) => WorkshopTypes::key($workshop) === $date->workshop_code);
    }
}
