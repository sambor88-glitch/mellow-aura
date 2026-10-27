<?php

namespace Tests\Feature\Workshops;

use App\Models\User;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Mail\BookingCancelled;
use App\Modules\Workshops\Mail\BookingConfirmed;
use App\Modules\Workshops\Mail\NewBookingReceived;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Booking a workshop with a voucher code instead of a payment (MA-137): the code is checked for its workshop and date,
 * books exactly the seats on the voucher, works once, and comes back when Kasia cancels the booking.
 */
class WorkshopVoucherBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
    }

    public function test_the_form_offers_a_voucher_only_for_a_workshop_that_has_vouchers(): void
    {
        $date = $this->date(seats: 4);

        $this->get(route('workshops.booking.create', $date))->assertOk()->assertDontSee('value="voucher"', false)->assertDontSee('Kod z vouchera');

        $this->voucher();

        $this->get(route('workshops.booking.create', $date))
            ->assertOk()
            ->assertSee('value="voucher"', false)
            ->assertSeeInOrder(['Mam voucher', 'Kod z vouchera', 'name="voucher_code"', 'Zapisuję się z voucherem'], false);
    }

    public function test_a_code_books_the_seats_on_the_voucher_at_once_and_is_used(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher(seats: 2);
        $typed = strtolower(str_replace('-', ' ', substr($voucher->code, 3)));

        $this->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $typed]))
            ->assertRedirect(route('workshops.booking.confirmation'));

        $order = Order::sole();
        $booking = WorkshopBooking::sole();
        $this->assertSame([PaymentMethod::Voucher, PaymentStatus::Paid, OrderStatus::Completed, 0, $voucher->code], [$order->payment_method, $order->payment_status, $order->status, $order->total_gross, $order->payment_provider_id]);
        $this->assertSame(['Lepienie kubków', 2, 0], [$order->items[0]->product_name, $order->items[0]->quantity, $order->items[0]->unit_price_gross]);
        $this->assertSame([BookingStatus::Confirmed, 2, $voucher->id], [$booking->status, $booking->seats, $booking->voucher_id]);
        $this->assertNotNull($voucher->fresh()->redeemed_at);
        $this->assertSame(2, $date->fresh()->seatsLeft());

        $this->get(route('workshops.booking.confirmation'))
            ->assertOk()
            ->assertSee('Do zobaczenia w pracowni')
            ->assertSee('miejsca: 2')
            ->assertSee('jest opłacony voucherem '.$voucher->code);

        Mail::assertQueued(BookingConfirmed::class, fn (BookingConfirmed $mail) => $mail->hasTo('ania@example.com'));
        (new BookingConfirmed($booking))->assertSeeInHtml('Liczba osób: 2 · voucher '.$voucher->code)->assertDontSeeInHtml('zapłacone');
        (new NewBookingReceived($booking))->assertSeeInText($order->number.' · voucher '.$voucher->code.', bez płatności.');
    }

    public function test_the_same_code_works_once_also_from_a_second_person(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher();
        $this->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $voucher->code]));

        $this->from(route('workshops.booking.create', $date))
            ->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $voucher->code, 'email' => 'druga@example.com']))
            ->assertRedirect(route('workshops.booking.create', $date))
            ->assertSessionHasErrors(['voucher_code' => 'Ten voucher jest już wykorzystany. Jeśli to pomyłka, napisz do mnie.']);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(3, $date->fresh()->seatsLeft());
    }

    public function test_a_code_that_books_nothing_says_why_and_saves_nothing(): void
    {
        $date = $this->date(seats: 4);
        $expired = $this->voucher(['valid_until' => '2026-10-25']);
        $couples = $this->voucher(['workshop_code' => 'couples']);
        $amount = $this->voucher(['workshop_code' => null, 'seats' => null]);
        // Terms §8.6: the workshop itself has to fall within the voucher's validity.
        $endsBefore = $this->voucher(['valid_until' => '2026-10-30']);

        $refusals = [
            'MA-ZZZZ-ZZZZ' => 'Nie znajduję takiego kodu. Przepisz go z vouchera — wygląda tak: MA-7KQ2-9XHT.',
            $expired->code => 'Ten voucher był ważny do 25 października 2026. Napisz do mnie — coś wymyślimy.',
            $couples->code => 'Ten voucher jest na warsztat „Warsztat dla pary”. Wybierz termin tego warsztatu.',
            $endsBefore->code => 'Ten voucher jest ważny do 30 października 2026 — wybierz termin do tego dnia.',
            $amount->code => 'Tym voucherem nie zapiszesz się przez stronę — napisz do mnie, a zapiszę Cię sama.',
        ];

        foreach ($refusals as $code => $message) {
            $this->from(route('workshops.booking.create', $date))
                ->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $code]))
                ->assertSessionHasErrors(['voucher_code' => $message]);
        }

        $this->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => '']))->assertSessionHasErrors(['voucher_code' => 'Wpisz kod z vouchera, np. MA-7KQ2-9XHT']);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, WorkshopBooking::query()->count());
        $this->assertSame(0, Voucher::query()->whereNotNull('redeemed_at')->count());
    }

    public function test_a_voucher_for_more_seats_than_are_left_stays_unused(): void
    {
        $date = $this->date(seats: 1);
        $voucher = $this->voucher(seats: 2);

        $this->from(route('workshops.booking.create', $date))
            ->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $voucher->code]))
            ->assertSessionHasErrors(['voucher_code' => 'Voucher jest na więcej miejsc, niż zostało na ten termin (ostatnie miejsce). Wybierz inny termin.']);

        $this->assertNull($voucher->fresh()->redeemed_at);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_guessing_codes_stops_after_five_tries_a_minute(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher();

        foreach (range(1, 5) as $try) {
            $this->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => 'MA-ZZZZ-ZZZ'.$try]))
                ->assertSessionHasErrors(['voucher_code' => 'Nie znajduję takiego kodu. Przepisz go z vouchera — wygląda tak: MA-7KQ2-9XHT.']);
        }

        $this->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $voucher->code]))
            ->assertSessionHasErrors(['voucher_code' => 'Za dużo prób z rzędu. Spróbuj znowu za minutę albo napisz do mnie.']);
        $this->assertNull($voucher->fresh()->redeemed_at);

        $this->travel(61)->seconds();
        $this->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $voucher->code]))
            ->assertRedirect(route('workshops.booking.confirmation'));
    }

    public function test_cancelling_gives_the_voucher_back_without_a_refund(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher(seats: 2);
        $this->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $voucher->code]));
        $booking = WorkshopBooking::sole();

        // A voucher used by a booking that stands stays used, whatever the panel form says.
        $this->actingAs(User::factory()->create())
            ->get(route('admin.gifts.edit', ['kod' => $voucher->code]))
            ->assertSeeInOrder(['Wykorzystany', 'zapis', $booking->order->number])
            ->assertDontSee('zapisałam ręcznie');
        $this->put(route('admin.gifts.voucher.update', $voucher), ['workshop_code' => 'hand_building', 'seats' => '2', 'valid_until' => '2027-10-26']);
        $this->assertNotNull($voucher->fresh()->redeemed_at);

        $this->get(route('admin.workshops.dates.bookings', $date))
            ->assertSee('voucher '.$voucher->code)
            ->assertSee('Wyślij klientce maila, że voucher znów jest ważny')
            ->assertDontSee('Anulowanie opłaconego zapisu nie oddaje pieniędzy samo');

        $this->post(route('admin.workshops.bookings.cancel', $booking), ['notify' => '1'])
            ->assertSessionHas('panel_status', 'Zapis anulowany, miejsce wolne. Voucher '.$voucher->code.' znów można wykorzystać.');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertNull($voucher->fresh()->redeemed_at);
        $this->assertSame(OrderStatus::Completed, $booking->order->fresh()->status);
        $this->assertSame(4, $date->fresh()->seatsLeft());

        Mail::assertQueued(BookingCancelled::class);
        (new BookingCancelled($booking->fresh()))
            ->assertSeeInHtml('Twój voucher '.$voucher->code.' znów jest ważny — do 26 października 2027.')
            ->assertDontSeeInHtml('Zwrócę Ci');

        // The mail tells how the voucher stood when it came back, even if the code books again before the queue sends it.
        $queued = new BookingCancelled($booking->fresh());

        $this->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $voucher->code]))
            ->assertRedirect(route('workshops.booking.confirmation'));
        $this->assertNotNull($voucher->fresh()->redeemed_at);
        $queued->assertSeeInHtml('znów jest ważny');
    }

    public function test_a_voucher_that_ran_out_meanwhile_comes_back_for_kasia_to_extend(): void
    {
        $date = $this->date(seats: 4, startsAt: '2026-11-20 11:00');
        $voucher = $this->voucher(['valid_until' => '2026-11-20']);
        $this->post(route('workshops.booking.store', $date), $this->form(['voucher_code' => $voucher->code]))->assertRedirect(route('workshops.booking.confirmation'));
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', 'Europe/Warsaw'));
        $voucher->update(['valid_until' => '2026-10-31']);

        $this->actingAs(User::factory()->create())->post(route('admin.workshops.bookings.cancel', WorkshopBooking::sole()), ['notify' => '1']);

        (new BookingCancelled(WorkshopBooking::sole()))->assertSeeInHtml('Jego ważność minęła 31 października 2026');
        $this->get(route('admin.gifts.edit', ['kod' => $voucher->code]))
            ->assertSee('Wygasł')
            ->assertSee('przesuń datę niżej');
    }

    private function date(int $seats, string $startsAt = '2026-10-31 11:00'): WorkshopDate
    {
        return WorkshopDate::factory()->create([
            'workshop_code' => 'hand_building',
            'starts_at' => $startsAt,
            'ends_at' => CarbonImmutable::parse($startsAt)->addHours(2),
            'seats' => $seats,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function voucher(array $attributes = [], int $seats = 1): Voucher
    {
        return Voucher::factory()->create(['workshop_code' => 'hand_building', 'seats' => $seats, 'valid_until' => '2027-10-26', ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'payment_method' => 'voucher',
            'accept_terms' => '1',
            ...$overrides,
        ];
    }
}
