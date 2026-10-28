<?php

namespace Tests\Feature\Workshops;

use App\Models\User;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\SimulatePayment;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Monitoring\Mail\Alert;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Booking a workshop date (MA-136): an order in Checkout with one item, paid like the shop, and the seats held in
 * workshop_bookings until the payment confirms them. Without Przelewy24's keys the stand-in gateway pays; its BLIK
 * codes are in SimulatePayment.
 */
class WorkshopBookingTest extends TestCase
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

    public function test_the_form_shows_the_date_the_price_and_only_online_payments(): void
    {
        $date = $this->date(seats: 3);

        $this->get(route('workshops.booking.create', $date))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex', false)
            ->assertSeeInOrder(['Lepienie kubków', 'sob. 31 października, 11:00 – 13:00', '2 godziny'], false)
            ->assertSee('Na ten termin: 3 wolne miejsca')
            ->assertSee('<option value="3"', false)
            ->assertDontSee('<option value="4"', false)
            ->assertSee('value="blik"', false)
            ->assertSee('value="card"', false)
            ->assertSee('value="online_transfer"', false)
            ->assertDontSee('value="bank_transfer"', false)
            ->assertSee('Rezerwuję i płacę')
            ->assertSee('220,00 zł')
            ->assertSee('Trzymam miejsce '.WorkshopBooking::HOLD_MINUTES.' minut na płatność.');
    }

    public function test_a_blik_payment_books_the_seats_and_the_page_counts_them_off(): void
    {
        $date = $this->date(seats: 3);
        // Something in the cart stays there: the booking is not a purchase from the shop.
        $this->withSession(['cart' => ['wrap' => ['type' => 'gift_wrap', 'quantity' => 1]]]);

        $this->post(route('workshops.booking.store', $date), $this->form(['seats' => 2, 'expected_total' => 44000]))
            ->assertRedirect(route('workshops.booking.confirmation'));

        $order = Order::sole();
        $booking = WorkshopBooking::sole();
        $this->assertSame([PaymentStatus::Paid, OrderStatus::Completed, ShippingMethods::EMAIL, 0, 44000], [$order->payment_status, $order->status, $order->shipping_method, $order->shipping_gross, $order->total_gross]);
        $this->assertSame(['Lepienie kubków', 'sob. 31 października, 11:00 – 13:00', 2, 22000, true], [
            $order->items[0]->product_name, $order->items[0]->variant_label, $order->items[0]->quantity, $order->items[0]->unit_price_gross, $order->items[0]->is_made_to_order,
        ]);
        $this->assertNotNull($order->terms_accepted_at);
        $this->assertSame([BookingStatus::Confirmed, 2, $order->id], [$booking->status, $booking->seats, $booking->order_id]);
        $this->assertNotNull($booking->confirmed_at);
        $this->assertSame(1, $date->fresh()->seatsLeft());

        $this->get(route('workshops.booking.confirmation'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Do zobaczenia w pracowni')
            ->assertSee($order->number)
            ->assertSee('miejsca: 2')
            ->assertSee('Pracownia: Kraków, okolice Błoń Krakowskich. Dokładny adres dostaniesz mailem.');

        $this->get('/warsztaty-ceramiczne-krakow')->assertSee('ostatnie miejsce');
        $this->assertSame(['wrap' => ['type' => 'gift_wrap', 'quantity' => 1]], session('cart'));
    }

    public function test_a_paid_booking_counts_once_as_workshop_booking_in_google_analytics(): void
    {
        Setting::query()->updateOrCreate(['key' => 'google_analytics_id'], ['value' => 'G-AB12CD34EF']);
        $this->post(route('workshops.booking.store', $this->date(seats: 3)), $this->form(['seats' => 2, 'expected_total' => 44000]));
        $order = Order::sole();

        $html = $this->get(route('workshops.booking.confirmation'))->assertOk()->getContent();
        preg_match('#<script type="application/json" data-analytics-event>(.*?)</script>#s', $html, $match);

        // The browser sends it once per order (analytics.js), with nothing the customer typed.
        $this->assertSame([
            'name' => 'workshop_booking',
            'params' => ['workshop_type' => 'hand_building', 'seats' => 2, 'method' => 'payment', 'currency' => 'PLN', 'value' => 440, 'transaction_id' => $order->number],
            'once' => 'workshop-'.$order->number,
        ], json_decode($match[1], true));
        $this->assertStringNotContainsString($order->email, $match[1]);
    }

    public function test_a_booking_still_waiting_for_its_payment_sends_nothing_to_google_analytics(): void
    {
        Setting::query()->updateOrCreate(['key' => 'google_analytics_id'], ['value' => 'G-AB12CD34EF']);
        $this->post(route('workshops.booking.store', $this->date(seats: 2)), $this->form(['payment_method' => 'card', 'blik_code' => null]));
        Order::sole()->update(['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        WorkshopBooking::sole()->update(['status' => BookingStatus::Held, 'confirmed_at' => null]);

        $this->get(route('workshops.booking.confirmation'))->assertOk()->assertDontSee('workshop_booking');
    }

    public function test_the_last_seat_goes_to_one_person_only(): void
    {
        $date = $this->date(seats: 1);
        $this->post(route('workshops.booking.store', $date), $this->form());

        $this->from(route('workshops.booking.create', $date))
            ->post(route('workshops.booking.store', $date), $this->form(['email' => 'druga@example.com']))
            ->assertRedirect(route('workshops.booking.create', $date))
            ->assertSessionHasErrors(['seats' => 'Ktoś zajął ostatnie miejsce chwilę przed Tobą — wybierz inny termin.']);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, $date->fresh()->seatsLeft());
        $this->get(route('workshops.booking.create', $date))->assertSee('Ten termin jest już pełny')->assertDontSee('Rezerwuję i płacę');
        $this->get('/warsztaty-ceramiczne-krakow')->assertSee('brak miejsc')->assertDontSee(route('workshops.booking.create', $date));
    }

    public function test_more_seats_than_are_free_are_refused_with_what_is_left(): void
    {
        $date = $this->date(seats: 2);

        $this->from(route('workshops.booking.create', $date))
            ->post(route('workshops.booking.store', $date), $this->form(['seats' => 3, 'expected_total' => 66000]))
            ->assertSessionHasErrors(['seats' => 'Zostało już tylko 2 wolne miejsca — zmniejsz liczbę miejsc.']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_code_the_bank_refuses_gives_the_seat_back_at_once(): void
    {
        $date = $this->date(seats: 1);

        $this->from(route('workshops.booking.create', $date))
            ->post(route('workshops.booking.store', $date), $this->form(['blik_code' => SimulatePayment::REJECTED_BLIK_CODE]))
            ->assertRedirect(route('workshops.booking.create', $date))
            ->assertSessionHasErrors('blik_code');

        $this->assertSame(BookingStatus::Released, WorkshopBooking::sole()->status);
        $this->assertSame(1, $date->fresh()->seatsLeft());
    }

    public function test_a_payment_waiting_in_the_bank_app_holds_the_seat_until_the_hold_runs_out(): void
    {
        $date = $this->date(seats: 1);

        $this->post(route('workshops.booking.store', $date), $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]))
            ->assertRedirect(route('checkout.blik'));

        $this->assertSame(BookingStatus::Held, WorkshopBooking::sole()->status);
        $this->assertSame(0, $date->fresh()->seatsLeft());

        // The BLIK page sends a booking back to its own form, not to the cart.
        $this->get(route('checkout.blik'))->assertOk()->assertSee('Potwierdź płatność w aplikacji banku');
        $this->travel(3)->minutes();
        $this->get(route('checkout.blik'))
            ->assertSee('miejsce z zapisu')
            ->assertSee('href="'.route('workshops.booking.create', $date).'"', false)
            ->assertDontSee('koszyk czekają');

        $this->travel(WorkshopBooking::HOLD_MINUTES)->minutes();
        $this->assertSame(1, $date->fresh()->seatsLeft());

        // Nobody took the seat meanwhile, so the late payment still gets it.
        app(MarkOrderPaid::class)(Order::sole(), 'test-late');

        $this->assertSame(BookingStatus::Confirmed, WorkshopBooking::sole()->status);
        $this->assertSame(OrderStatus::Completed, Order::sole()->status);
        $this->assertSame(0, $date->fresh()->seatsLeft());
    }

    public function test_a_late_payment_for_a_seat_someone_else_took_is_overbooked_and_kasia_hears_about_it(): void
    {
        config(['monitoring.alert_email' => 'alerty@example.com']);
        $date = $this->date(seats: 1);

        $this->post(route('workshops.booking.store', $date), $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));
        $late = Order::sole();

        $this->travel(WorkshopBooking::HOLD_MINUTES + 1)->minutes();
        $this->flushSession();
        $this->post(route('workshops.booking.store', $date), $this->form(['email' => 'druga@example.com']))
            ->assertRedirect(route('workshops.booking.confirmation'));

        app(MarkOrderPaid::class)($late, 'test-late');

        $booking = WorkshopBooking::query()->where('order_id', $late->id)->sole();
        $this->assertSame(BookingStatus::Overbooked, $booking->status);
        $this->assertSame([PaymentStatus::Paid, OrderStatus::Problem], [$late->fresh()->payment_status, $late->fresh()->status]);
        $this->assertStringStartsWith('Nadkomplet', $late->fresh()->problem_note);
        $this->assertSame(0, $date->fresh()->seatsLeft());
        Mail::assertSent(Alert::class, fn (Alert $alert) => $alert->hasTo('alerty@example.com'));

        $this->withSession(['checkout.order' => $late->number])
            ->get(route('workshops.booking.confirmation'))
            ->assertOk()
            ->assertSee('Przepraszam — termin się zapełnił')
            ->assertSee('zwrócę całą wpłatę');
    }

    public function test_a_failed_payment_frees_the_seat_and_goes_back_to_the_form(): void
    {
        $date = $this->date(seats: 1);
        $this->post(route('workshops.booking.store', $date), $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));
        Order::sole()->update(['payment_status' => PaymentStatus::Failed]);

        // Przelewy24 sends everyone back to the shop's confirmation; a booking moves on to its own.
        $this->get(route('checkout.confirmation'))->assertRedirect(route('workshops.booking.confirmation'));
        $this->get(route('workshops.booking.confirmation'))
            ->assertRedirect(route('workshops.booking.create', $date))
            ->assertSessionHas('booking_notice');

        $this->assertSame(BookingStatus::Released, WorkshopBooking::sole()->status);
        $this->assertSame(1, $date->fresh()->seatsLeft());
    }

    public function test_a_payment_still_on_its_way_shows_that_the_seat_waits(): void
    {
        $date = $this->date(seats: 2);
        $this->post(route('workshops.booking.store', $date), $this->form(['payment_method' => 'card', 'blik_code' => null]));
        Order::sole()->update(['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        WorkshopBooking::sole()->update(['status' => BookingStatus::Held, 'confirmed_at' => null]);

        $this->get(route('workshops.booking.confirmation'))
            ->assertOk()
            ->assertSee('Czekam na potwierdzenie płatności')
            ->assertSee('Sprawdź jeszcze raz');
    }

    public function test_a_bank_transfer_and_a_changed_price_are_refused(): void
    {
        $date = $this->date(seats: 2);

        $this->post(route('workshops.booking.store', $date), $this->form(['payment_method' => 'bank_transfer']))
            ->assertSessionHasErrors('payment_method');
        $this->post(route('workshops.booking.store', $date), $this->form(['expected_total' => 19000]))
            ->assertSessionHas('booking_notice');
        $this->post(route('workshops.booking.store', $date), $this->form(['accept_terms' => null, 'name' => '']))
            ->assertSessionHasErrors(['accept_terms', 'name']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_date_that_has_passed_or_left_the_price_list_cannot_be_booked(): void
    {
        $past = $this->date(seats: 3, startsAt: '2026-10-26 10:00');
        $removed = WorkshopDate::factory()->create(['workshop_code' => 'removed', 'starts_at' => '2026-11-10 11:00', 'seats' => 3]);

        $this->get(route('workshops.booking.create', $past))->assertRedirect(route('workshops.index'));
        $this->get(route('workshops.booking.create', $removed))->assertRedirect(route('workshops.index'));
        $this->post(route('workshops.booking.store', $past), $this->form())->assertRedirect(route('workshops.index'));

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_date_with_bookings_stays_in_the_calendar(): void
    {
        $date = $this->date(seats: 2);
        $this->post(route('workshops.booking.store', $date), $this->form());

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.workshops.dates.destroy', $date))
            ->assertSessionHas('panel_status', 'Na ten termin są zapisy — anuluj je w „Zapisy”, zanim usuniesz termin');

        $this->assertModelExists($date);
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
