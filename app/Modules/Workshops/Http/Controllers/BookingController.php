<?php

namespace App\Modules\Workshops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\SettlePayment;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Http\Controllers\BlikController;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\VoucherCodes;
use App\Modules\Content\Support\LegalDocument;
use App\Modules\Gifts\Actions\RedeemVoucher;
use App\Modules\Gifts\Actions\RestoreVoucherBalance;
use App\Modules\Gifts\Enums\BalanceRefusal;
use App\Modules\Gifts\Enums\VoucherRefusal;
use App\Modules\Gifts\Exceptions\BalanceNotAccepted;
use App\Modules\Gifts\Exceptions\VoucherNotAccepted;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherBalance;
use App\Modules\Gifts\Support\VoucherCode;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Settings\Settings;
use App\Modules\Workshops\Actions\PlaceBooking;
use App\Modules\Workshops\Actions\ReleaseBooking;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Http\Requests\BookWorkshopRequest;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\BookingCalendar;
use App\Modules\Workshops\Support\WorkshopTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Booking a seat on a workshop date (/warsztaty-ceramiczne-krakow/zapis/{date}). The booking is an order in Checkout
 * with one item and nothing to deliver, paid the way the shop is paid: the same gateway, the same BLIK page and the
 * same notification from Przelewy24. The seats are held while the payment goes (PlaceBooking) and become hers when it
 * is confirmed (ConfirmBooking, from MarkOrderPaid). Search engines never index these pages.
 *
 * One field takes any voucher code (MA-244): a workshop voucher books the seats printed on it and pays for them
 * (MA-137); amount vouchers pay their balance towards the sum and leave the rest for the gateway. The codes travel with
 * the form, so nothing is kept in the session until the booking is placed.
 */
class BookingController extends Controller
{
    /** The most seats one booking takes through the site; a bigger group writes to Kasia. */
    public const MAX_SEATS = 6;

    private const ROBOTS = 'noindex, nofollow';

    /** Wrong voucher codes allowed from one address per minute. */
    private const VOUCHER_ATTEMPTS = 5;

    public function __construct(private RestoreVoucherBalance $restoreVoucherBalance) {}

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
        $codes = $this->codes(old('vouchers'));
        // A workshop voucher books by itself; otherwise the amount vouchers typed so far, in the order typed.
        $workshopVoucher = Voucher::query()->whereIn('code', $codes)->whereNull('amount_minor')->whereNull('redeemed_at')->first();
        $chosen = PaymentMethod::tryFrom((string) old('payment_method'));

        return response()->view('workshops::booking', [
            'date' => $date,
            'workshop' => $workshop,
            'seatsLeft' => $seatsLeft = $date->seatsLeft(),
            'maxSeats' => min($seatsLeft, self::MAX_SEATS),
            'paymentMethods' => $methods,
            'selectedPayment' => ($chosen !== null && $methods->contains($chosen) ? $chosen : $methods->first())->value,
            'blikCode' => $gateway->takesBlikCode(),
            // „Masz kod vouchera?” only once there is a voucher it could take: one for this workshop or one with a balance.
            'takesVouchers' => Voucher::query()->where(fn ($voucher) => $voucher->where('workshop_code', $date->workshop_code)->orWhereNotNull('amount_minor'))->exists(),
            'workshopVoucher' => $workshopVoucher,
            'amountVouchers' => $workshopVoucher === null ? $this->amountVouchers($codes) : collect(),
            'location' => $settings->get('location_description'),
            'googleCalendar' => $paid && ! $overbooked ? $calendar->googleUrl($booking) : null,
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    /**
     * The paid booking from the session as an event for her calendar (MA-268). The site never gives the exact address,
     * so the file has the area from the panel; the one attached to the confirmation mail has the address.
     */
    public function calendar(Request $request, BookingCalendar $calendar): Response
    {
        $number = $request->session()->get('checkout.order');
        $booking = $number ? Order::query()->where('number', $number)->first()?->workshopBooking : null;

        abort_if($booking === null || $booking->status !== BookingStatus::Confirmed || $booking->workshopDate === null, 404);

        return response($calendar->ics($booking), 200, [
            'Content-Type' => BookingCalendar::mime(),
            'Content-Disposition' => 'attachment; filename="'.BookingCalendar::FILE_NAME.'"',
            'X-Robots-Tag' => self::ROBOTS,
        ]);
    }

    /**
     * „Użyj vouchera”: the whole form comes here, so everything typed so far comes back filled in. The code is checked
     * for this date and kept with the form; a workshop voucher takes the place of the payment, an amount voucher joins
     * the others. Wrong codes count towards the same limit as at the booking.
     */
    public function addVoucher(Request $request, WorkshopDate $date, WorkshopTypes $types, RedeemVoucher $redeemVoucher): RedirectResponse
    {
        if ($this->workshop($date, $types) === null) {
            return to_route('workshops.index');
        }

        // A form left for Przelewy24's page and come back to: its unpaid booking gives the voucher money back first.
        $this->releaseVoucherHolds($request);

        $code = trim((string) $request->input('voucher_code'));
        $kept = $this->codes($request->input('vouchers'));
        $typed = $request->except(['_token', 'blik_code', 'voucher_code', 'vouchers']);
        $refuse = fn (string $message) => to_route('workshops.booking.create', $date)
            ->withInput([...$typed, 'vouchers' => $kept, 'voucher_code' => $code])->withErrors(['voucher_code' => $message]);
        $attempts = 'workshop-voucher:'.$request->ip();

        if ($code === '') {
            return $refuse(__('checkout::checkout.voucher_refused.empty'));
        }

        if (RateLimiter::tooManyAttempts($attempts, self::VOUCHER_ATTEMPTS)) {
            return $refuse('Za dużo prób z rzędu. Spróbuj znowu za minutę albo napisz do mnie.');
        }

        $voucher = Voucher::query()->where('code', VoucherCode::normalize($code))->first();

        try {
            if ($voucher !== null && ! $voucher->isAmount()) {
                DB::transaction(fn () => $redeemVoucher->lockFor($code, $date->workshop_code, $date->starts_at));

                return to_route('workshops.booking.create', $date)
                    ->withInput([...$typed, 'payment_method' => PaymentMethod::Voucher->value, 'vouchers' => [$voucher->code]]);
            }

            $voucher = VoucherBalance::usable($code, $voucher);
            throw_if(in_array($voucher->code, $kept, true), new BalanceNotAccepted($code, BalanceRefusal::Twice, $voucher));
        } catch (VoucherNotAccepted $refused) {
            RateLimiter::hit($attempts);

            return $refuse($this->refusal($refused, $types));
        } catch (BalanceNotAccepted $refused) {
            RateLimiter::hit($attempts);

            return $refuse(VoucherCodes::refusal($refused));
        }

        if (count($kept) >= VoucherBalance::MAX_CODES) {
            return $refuse(__('checkout::checkout.voucher_refused.too_many', ['count' => VoucherBalance::MAX_CODES]));
        }

        return to_route('workshops.booking.create', $date)->withInput([...$typed, 'vouchers' => [...$kept, $voucher->code]]);
    }

    /** „Nie używaj vouchera”: the code leaves the form; without a workshop voucher, a way to pay is chosen again. */
    public function removeVoucher(Request $request, WorkshopDate $date): RedirectResponse
    {
        $typed = $request->except(['_token', 'blik_code', 'voucher_code', 'vouchers', 'remove_voucher']);
        $kept = array_values(array_diff($this->codes($request->input('vouchers')), [(string) $request->input('remove_voucher')]));

        if (($typed['payment_method'] ?? null) === PaymentMethod::Voucher->value) {
            unset($typed['payment_method']);
        }

        return to_route('workshops.booking.create', $date)->withInput([...$typed, 'vouchers' => $kept]);
    }

    public function store(BookWorkshopRequest $request, WorkshopDate $date, WorkshopTypes $types, PlaceBooking $placeBooking, ReleaseBooking $release, PaymentGateway $gateway): RedirectResponse
    {
        $workshop = $this->workshop($date, $types);

        if ($workshop === null) {
            return to_route('workshops.index');
        }

        $data = $request->validated();
        $this->releaseVoucherHolds($request);

        if ($data['payment_method'] === PaymentMethod::Voucher->value) {
            return $this->storeWithVoucher($request, $date, $workshop, $data, $types, $placeBooking);
        }
        $seats = (int) $data['seats'];

        // She pays the sum the screen showed. If Kasia changed the price meanwhile, show the new one first.
        if ((int) $data['expected_total'] !== $workshop['price_gross'] * $seats) {
            return back()->withInput()->with('booking_notice', 'Cena warsztatu właśnie się zmieniła — sprawdź nową kwotę i zapisz się jeszcze raz.');
        }

        // The same goes for the amount vouchers: one spent elsewhere meanwhile leaves more to pay (MA-244).
        $codes = $this->codes($data['vouchers'] ?? []);
        $vouchers = VoucherBalance::preview($codes, $workshop['price_gross'] * $seats);
        $vouchersGross = (int) $vouchers->sum('amount');
        $changed = fn () => back()->withInput([...$request->except(['blik_code', 'vouchers']), 'vouchers' => $vouchers->map(fn (array $use) => $use['voucher']->code)->all()])
            ->with('booking_notice', 'Na voucherze jest teraz inna kwota — sprawdź, ile zostaje do zapłaty, i zapisz się jeszcze raz.');

        if ((int) ($data['expected_vouchers'] ?? 0) !== $vouchersGross) {
            return $changed();
        }

        try {
            $booking = $placeBooking($date, $workshop, $seats, [...$data, 'terms_version' => LegalDocument::terms()->versionLabel()], $codes);
        } catch (BalanceNotAccepted $refused) {
            return back()->withInput([...$request->except(['blik_code', 'vouchers']), 'vouchers' => array_values(array_diff($codes, [VoucherCode::normalize($refused->typedCode)]))])
                ->withErrors(['voucher_code' => VoucherCodes::refusal($refused)]);
        }

        if ($booking === null) {
            $left = $date->seatsLeft();

            return back()->withInput()->withErrors(['seats' => $left === 0
                ? 'Ktoś zajął ostatnie miejsce chwilę przed Tobą — wybierz inny termin.'
                : 'Zostało już tylko '.$date->seatsLeftLabel().' — zmniejsz liczbę miejsc.']);
        }

        $order = $booking->order;

        // Vouchers paid for everything: the seats are hers already, nothing goes through the gateway.
        if ($order->payment_status === PaymentStatus::Paid) {
            $request->session()->put('checkout.order', $order->number);

            return to_route('workshops.booking.confirmation');
        }

        // Another booking took the voucher's money between the check above and the lock in PlaceBooking.
        if ($order->vouchers_gross !== $vouchersGross) {
            $release($order);

            return $changed();
        }
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
    public function confirmation(Request $request, SettlePayment $settle, ReleaseBooking $release, PaymentGateway $gateway, Settings $settings, BookingCalendar $calendar): Response|RedirectResponse
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
            'method' => $booking->voucher_id !== null || $order->amountDue() === 0 ? 'voucher' : 'payment',
            'currency' => $order->currency ?? 'PLN',
            'value' => $order->total_gross / 100,
            'transaction_id' => $order->number,
        ];
    }

    /**
     * The last booking this session placed, if it is still waiting for the payment and the customer is booking again:
     * the voucher money it holds is free again. Its seats wait out their hold as before, and a payment that comes later
     * takes the money again if the voucher still has it (Gifts\Actions\CaptureVoucherBalance). An order from the shop's
     * checkout is left alone.
     */
    private function releaseVoucherHolds(Request $request): void
    {
        $number = $request->session()->get('checkout.order');
        $previous = $number === null ? null : Order::query()->where('number', $number)->where('payment_status', '!=', PaymentStatus::Paid)->first();

        if ($previous?->workshopBooking !== null) {
            $this->restoreVoucherBalance->abandoned($previous);
        }
    }

    /**
     * The voucher codes the form carries, as typed into the page.
     *
     * @return list<string>
     */
    private function codes(mixed $codes): array
    {
        return array_values(array_unique(array_filter((array) $codes, fn ($code) => is_string($code) && $code !== '')));
    }

    /**
     * The amount vouchers that can pay now, in the order typed. A code that stopped paying since drops out quietly;
     * the sum on the screen shows it.
     *
     * @param  list<string>  $codes
     * @return Collection<int, Voucher>
     */
    private function amountVouchers(array $codes): Collection
    {
        return VoucherBalance::preview($codes, PHP_INT_MAX)->pluck('voucher')->values();
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
