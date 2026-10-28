<?php

namespace App\Modules\Workshops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\SettlePayment;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Http\Controllers\BlikController;
use App\Modules\Checkout\Models\Order;
use App\Modules\Content\Support\LegalDocument;
use App\Modules\Gifts\Enums\VoucherRefusal;
use App\Modules\Gifts\Exceptions\VoucherNotAccepted;
use App\Modules\Gifts\Models\Voucher;
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
use Illuminate\Support\Facades\RateLimiter;

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

    /** Wrong voucher codes allowed from one address per minute. */
    private const VOUCHER_ATTEMPTS = 5;

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
        // „Mam voucher” only for a workshop that vouchers were issued for.
        $takesVouchers = Voucher::query()->where('workshop_code', $date->workshop_code)->exists();

        if ($takesVouchers) {
            $methods->push(PaymentMethod::Voucher);
        }

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

        if ($data['payment_method'] === PaymentMethod::Voucher->value) {
            return $this->storeWithVoucher($request, $date, $workshop, $data, $types, $placeBooking);
        }
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
     * A booking paid with a voucher code (MA-137): confirmed at once, or back to the form with why the code books
     * nothing. Wrong codes from one address are limited, so codes cannot be guessed by trying.
     *
     * @param  array{name: string, price_gross: int}  $workshop
     * @param  array<string, mixed>  $data
     */
    private function storeWithVoucher(Request $request, WorkshopDate $date, array $workshop, array $data, WorkshopTypes $types, PlaceBooking $placeBooking): RedirectResponse
    {
        $attempts = 'workshop-voucher:'.$request->ip();

        if (RateLimiter::tooManyAttempts($attempts, self::VOUCHER_ATTEMPTS)) {
            return back()->withInput()->withErrors(['voucher_code' => 'Za dużo prób z rzędu. Spróbuj znowu za minutę albo napisz do mnie.']);
        }

        try {
            $booking = $placeBooking->withVoucher($date, $workshop, (string) $data['voucher_code'], [...$data, 'terms_version' => LegalDocument::terms()->versionLabel()]);
        } catch (VoucherNotAccepted $refused) {
            RateLimiter::hit($attempts);

            return back()->withInput()->withErrors(['voucher_code' => $this->refusal($refused, $types)]);
        }

        if ($booking === null) {
            $left = $date->seatsLeft();

            return back()->withInput()->withErrors(['voucher_code' => $left === 0
                ? 'Ktoś zajął ostatnie miejsce chwilę przed Tobą — wybierz inny termin.'
                : 'Voucher jest na więcej miejsc, niż zostało na ten termin ('.$date->seatsLeftLabel().'). Wybierz inny termin.']);
        }

        $request->session()->put('checkout.order', $booking->order->number);

        return to_route('workshops.booking.confirmation');
    }

    /** Why a code books nothing, in words. An unknown code says nothing about how close it was. */
    private function refusal(VoucherNotAccepted $refused, WorkshopTypes $types): string
    {
        $voucherFor = $types->all()->first(fn (array $workshop) => WorkshopTypes::key($workshop) === $refused->voucher?->workshop_code)['name'] ?? null;

        return match ($refused->reason) {
            VoucherRefusal::Unknown => 'Nie znajduję takiego kodu. Przepisz go z vouchera — wygląda tak: MA-7KQ2-9XHT.',
            VoucherRefusal::Redeemed => 'Ten voucher jest już wykorzystany. Jeśli to pomyłka, napisz do mnie.',
            VoucherRefusal::Expired => 'Ten voucher był ważny do '.$refused->voucher->valid_until->translatedFormat('j F Y').'. Napisz do mnie — coś wymyślimy.',
            VoucherRefusal::EndsBeforeDate => 'Ten voucher jest ważny do '.$refused->voucher->valid_until->translatedFormat('j F Y').' — wybierz termin do tego dnia.',
            VoucherRefusal::OtherWorkshop => $voucherFor !== null
                ? 'Ten voucher jest na warsztat „'.$voucherFor.'”. Wybierz termin tego warsztatu.'
                : 'Ten voucher jest na inny warsztat. Napisz do mnie, a zapiszę Cię sama.',
            VoucherRefusal::NoWorkshop => 'Tym voucherem nie zapiszesz się przez stronę — napisz do mnie, a zapiszę Cię sama.',
        };
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
        $booking = $booking->fresh(['workshopDate', 'voucher']);

        $paid = $order->payment_status === PaymentStatus::Paid;
        $overbooked = $booking->status === BookingStatus::Overbooked;

        return response()->view('workshops::booking-confirmation', [
            'order' => $order,
            'booking' => $booking,
            'date' => $booking->workshopDate,
            'paid' => $paid,
            'overbooked' => $overbooked,
            'analytics' => $paid && ! $overbooked ? $this->analytics($order, $booking) : null,
            'location' => $settings->get('location_description'),
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    /**
     * workshop_booking for Google Analytics (MA-140): which workshop, how many seats, what it was worth and whether
     * money or a voucher paid. Nothing the customer typed, no name and no e-mail.
     *
     * @return array<string, string|int|float>
     */
    private function analytics(Order $order, WorkshopBooking $booking): array
    {
        return [
            'workshop_type' => $booking->workshopDate->workshop_code,
            'seats' => $booking->seats,
            'method' => $booking->voucher_id !== null ? 'voucher' : 'payment',
            'currency' => $order->currency ?? 'PLN',
            'value' => $order->total_gross / 100,
            'transaction_id' => $order->number,
        ];
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
