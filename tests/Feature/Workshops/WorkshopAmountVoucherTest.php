<?php

namespace Tests\Feature\Workshops;

use App\Models\User;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\SimulatePayment;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Enums\RedemptionStatus;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Models\VoucherRedemption;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Mail\BookingCancelled;
use App\Modules\Workshops\Mail\BookingConfirmed;
use App\Modules\Workshops\Mail\BookingOverbooked;
use App\Modules\Workshops\Mail\NewBookingReceived;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An amount voucher at the workshop booking (MA-244): its balance pays first, held with the seats, and only the rest
 * goes through the gateway. A refused payment, a cancelled booking and a booking that came too late give the voucher
 * its money back or tell Kasia to.
 */
class WorkshopAmountVoucherTest extends TestCase
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

    public function test_a_voucher_that_covers_the_seats_books_them_at_once_and_keeps_the_rest(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher(30000);
        Setting::query()->updateOrCreate(['key' => 'google_analytics_id'], ['value' => 'G-AB12CD34EF']);

        $this->post(route('workshops.booking.voucher.add', $date), ['voucher_code' => strtolower(str_replace('-', ' ', $voucher->code)), 'email' => 'ania@example.com', 'seats' => '1'])
            ->assertRedirect(route('workshops.booking.create', $date))
            ->assertSessionHasInput('vouchers', [$voucher->code]);

        $this->get(route('workshops.booking.create', $date))
            ->assertOk()
            ->assertSee('name="vouchers[]" value="'.$voucher->code.'"', false)
            ->assertSee('name="expected_vouchers" value="22000"', false)
            ->assertSeeInOrder(['Voucher '.$voucher->code, 'zostanie na nim 80 zł', 'Do zapłaty', '0 zł', 'Voucher pokrywa całość', 'Zapisuję się, płaci voucher']);

        // No BLIK code: there is nothing left to pay.
        $this->post(route('workshops.booking.store', $date), $this->form(['vouchers' => [$voucher->code], 'expected_vouchers' => 22000, 'blik_code' => '']))
            ->assertRedirect(route('workshops.booking.confirmation'));

        $order = Order::sole();
        $booking = WorkshopBooking::sole();
        $this->assertSame([PaymentStatus::Paid, PaymentMethod::Voucher, OrderStatus::Completed, 22000, 0], [$order->payment_status, $order->payment_method, $order->status, $order->vouchers_gross, $order->amountDue()]);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(8000, $voucher->fresh()->balance_minor);
        $this->assertSame(RedemptionStatus::Used, VoucherRedemption::sole()->status);
        $this->assertSame(3, $date->fresh()->seatsLeft());

        $this->get(route('workshops.booking.confirmation'))
            ->assertOk()
            ->assertSee('Do zobaczenia w pracowni')
            ->assertSee('"method":"voucher"', false);
        (new BookingConfirmed($booking))->assertSeeInHtml('Liczba osób: 1 · zapłacone voucherem 220 zł');
        (new NewBookingReceived($booking))->assertSeeInText($order->number.' · zapłacone voucherem 220 zł.');
    }

    public function test_a_voucher_that_covers_part_leaves_the_rest_for_the_gateway_and_is_spent_on_payment(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher(10000);

        $this->post(route('workshops.booking.store', $date), $this->form(['seats' => 2, 'expected_total' => 44000, 'vouchers' => [$voucher->code], 'expected_vouchers' => 10000, 'blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]))
            ->assertRedirect(route('checkout.blik'));

        $order = Order::sole();
        $this->assertSame([PaymentStatus::Pending, PaymentMethod::Blik, 10000, 34000], [$order->payment_status, $order->payment_method, $order->vouchers_gross, $order->amountDue()]);
        $this->assertSame([0, RedemptionStatus::Held], [$voucher->fresh()->balance_minor, VoucherRedemption::sole()->status]);
        $this->assertSame(BookingStatus::Held, WorkshopBooking::sole()->status);

        app(MarkOrderPaid::class)($order, 'p24-1');

        $booking = WorkshopBooking::sole();
        $this->assertSame([BookingStatus::Confirmed, RedemptionStatus::Used, 0], [$booking->status, VoucherRedemption::sole()->status, $voucher->fresh()->balance_minor]);
        (new BookingConfirmed($booking))->assertSeeInHtml('Liczba osób: 2 · zapłacone 340 zł i voucherem 100 zł');
        (new NewBookingReceived($booking))->assertSeeInText($order->number.' · zapłacone 340 zł i voucherem 100 zł, BLIK.');
    }

    public function test_a_refused_payment_gives_the_seats_and_the_money_back_to_the_voucher(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher(10000);

        $this->from(route('workshops.booking.create', $date))
            ->post(route('workshops.booking.store', $date), $this->form(['vouchers' => [$voucher->code], 'expected_vouchers' => 10000, 'blik_code' => SimulatePayment::REJECTED_BLIK_CODE]))
            ->assertRedirect(route('workshops.booking.create', $date))
            ->assertSessionHasErrors('blik_code');

        $this->assertSame(BookingStatus::Released, WorkshopBooking::sole()->status);
        $this->assertSame([10000, RedemptionStatus::Returned], [$voucher->fresh()->balance_minor, VoucherRedemption::sole()->status]);
        $this->assertSame(4, $date->fresh()->seatsLeft());
    }

    public function test_a_voucher_spent_meanwhile_shows_the_new_sum_before_anything_is_booked(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher(10000);
        $voucher->update(['balance_minor' => 4000]);

        $this->from(route('workshops.booking.create', $date))
            ->post(route('workshops.booking.store', $date), $this->form(['vouchers' => [$voucher->code], 'expected_vouchers' => 10000]))
            ->assertRedirect(route('workshops.booking.create', $date))
            ->assertSessionHas('booking_notice')
            ->assertSessionHasInput('vouchers', [$voucher->code]);

        // A code that stopped paying altogether says why under the field.
        $voucher->update(['valid_until' => '2026-10-25']);
        $this->from(route('workshops.booking.create', $date))
            ->post(route('workshops.booking.store', $date), $this->form(['vouchers' => [$voucher->code], 'expected_vouchers' => 0]))
            ->assertSessionHasErrors(['voucher_code' => 'Ten voucher był ważny do 25 października 2026. Napisz do mnie — coś wymyślimy.'])
            ->assertSessionHasInput('vouchers', []);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_the_field_takes_several_amount_vouchers_and_refuses_one_twice(): void
    {
        $date = $this->date(seats: 4);
        $first = $this->voucher(15000);
        $second = $this->voucher(15000);

        $this->post(route('workshops.booking.voucher.add', $date), ['voucher_code' => $second->code, 'vouchers' => [$first->code], 'seats' => '2'])
            ->assertSessionHasInput('vouchers', [$first->code, $second->code]);
        $this->get(route('workshops.booking.create', $date))
            ->assertSee('name="expected_vouchers" value="30000"', false)
            ->assertSeeInOrder(['Voucher '.$first->code, 'cały idzie na ten zapis', 'Voucher '.$second->code, 'Do zapłaty', '140 zł', 'Rezerwuję i płacę']);

        $this->post(route('workshops.booking.voucher.add', $date), ['voucher_code' => $first->code, 'vouchers' => [$first->code]])
            ->assertSessionHasErrors(['voucher_code' => 'Ten voucher już odjęłam od sumy.']);
        $this->post(route('workshops.booking.voucher.add', $date), ['voucher_code' => 'MA-ZZZZ-ZZZZ'])
            ->assertSessionHasErrors(['voucher_code' => 'Nie znajduję takiego kodu. Przepisz go z vouchera — wygląda tak: MA-7KQ2-9XHT.']);

        $this->post(route('workshops.booking.voucher.remove', $date), ['vouchers' => [$first->code, $second->code], 'remove_voucher' => $first->code])
            ->assertSessionHasInput('vouchers', [$second->code]);
    }

    public function test_cancelling_a_booking_gives_the_voucher_its_money_back_and_kasia_returns_only_the_rest(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher(10000);
        $this->post(route('workshops.booking.store', $date), $this->form(['vouchers' => [$voucher->code], 'expected_vouchers' => 10000]))
            ->assertRedirect(route('workshops.booking.confirmation'));
        $booking = WorkshopBooking::sole();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);

        $this->actingAs(User::factory()->create())->post(route('admin.workshops.bookings.cancel', $booking), ['notify' => '1']);

        $order = $booking->order->fresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame([10000, RedemptionStatus::Returned], [$voucher->fresh()->balance_minor, VoucherRedemption::sole()->status]);
        $this->assertSame(OrderStatus::Problem, $order->status);
        $this->assertSame('Zapis na warsztat anulowany w panelu. Zwróć wpłatę 120 zł, potem zmień status na „Zakończone”. 100 zł z vouchera wróciło już na jego saldo.', $order->problem_note);

        Mail::assertQueued(BookingCancelled::class);
        (new BookingCancelled($booking->fresh()))
            ->assertSeeInHtml('Zwrócę Ci wpłatę, 120 zł, najpóźniej w ciągu 14 dni')
            ->assertSeeInHtml('Kwota z vouchera, 100 zł, wróciła już na voucher')
            ->assertSeeInText('Zwrócę Ci wpłatę, 120 zł');
    }

    public function test_cancelling_a_booking_the_voucher_paid_in_full_returns_no_money(): void
    {
        $date = $this->date(seats: 4);
        $voucher = $this->voucher(22000);
        $this->post(route('workshops.booking.store', $date), $this->form(['vouchers' => [$voucher->code], 'expected_vouchers' => 22000, 'blik_code' => '']));
        $booking = WorkshopBooking::sole();

        $this->actingAs(User::factory()->create())->post(route('admin.workshops.bookings.cancel', $booking), ['notify' => '1']);

        $this->assertSame(22000, $voucher->fresh()->balance_minor);
        $this->assertSame(OrderStatus::Completed, $booking->order->fresh()->status);
        (new BookingCancelled($booking->fresh()))
            ->assertDontSeeInHtml('Zwrócę Ci')
            ->assertSeeInHtml('Kwota z vouchera, 220 zł, wróciła już na voucher');
    }

    public function test_a_late_payment_for_a_full_date_tells_kasia_to_give_the_voucher_part_back(): void
    {
        $date = $this->date(seats: 1);
        $voucher = $this->voucher(10000);
        $this->post(route('workshops.booking.store', $date), $this->form(['vouchers' => [$voucher->code], 'expected_vouchers' => 10000, 'blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));
        $late = Order::sole();

        $this->travel(WorkshopBooking::HOLD_MINUTES + 1)->minutes();
        $this->flushSession();
        $this->post(route('workshops.booking.store', $date), $this->form(['email' => 'druga@example.com']))->assertRedirect(route('workshops.booking.confirmation'));

        app(MarkOrderPaid::class)($late, 'p24-late');

        $late = $late->fresh();
        $this->assertSame(BookingStatus::Overbooked, WorkshopBooking::query()->where('order_id', $late->id)->sole()->status);
        $this->assertSame(OrderStatus::Problem, $late->status);
        $this->assertStringContainsString('Zwróć wpłatę 120 zł i oddaj 100 zł na voucher albo zaproponuj inny termin.', $late->problem_note);
        // The voucher part stays spent until Kasia decides: a seat on another date keeps it.
        $this->assertSame([0, RedemptionStatus::Used], [$voucher->fresh()->balance_minor, VoucherRedemption::sole()->status]);

        (new BookingOverbooked(WorkshopBooking::query()->where('order_id', $late->id)->sole()))
            ->assertSeeInHtml('Zwrócę Ci wpłatę, 120 zł, najpóźniej w ciągu 14 dni. Kwotę z vouchera, 100 zł, oddam na voucher.');
    }

    private function date(int $seats): WorkshopDate
    {
        return WorkshopDate::factory()->create([
            'workshop_code' => 'hand_building',
            'starts_at' => '2026-10-31 11:00',
            'ends_at' => '2026-10-31 13:00',
            'seats' => $seats,
        ]);
    }

    private function voucher(int $amount): Voucher
    {
        return Voucher::factory()->create(['workshop_code' => null, 'seats' => null, 'amount_minor' => $amount, 'balance_minor' => $amount, 'valid_until' => '2027-10-26']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_filter([
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'seats' => 1,
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'expected_total' => 22000,
            'accept_terms' => '1',
            ...$overrides,
        ], fn (mixed $value) => $value !== null);
    }
}
