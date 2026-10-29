<?php

namespace Tests\Feature\Workshops;

use App\Models\User;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\SimulatePayment;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Mail\NewOrderReceived;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Mail\Alert;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Workshops\Actions\SendReminders;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Mail\BookingCancelled;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Who is coming to a date, and cancelling a booking from the panel (MA-139): the seat is free at once,
 * the money goes back by hand from the order under „Problem”, and she hears about it if Kasia wants.
 */
class WorkshopBookingsPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        config(['monitoring.alert_email' => 'alerty@example.com']);
        $this->owner = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
    }

    public function test_only_the_owner_sees_the_bookings_and_cancels_them(): void
    {
        $date = $this->date(seats: 3);
        $booking = $this->book($date);

        $this->get(route('admin.workshops.dates.bookings', $date))->assertRedirect('/panel/logowanie');
        $this->post(route('admin.workshops.bookings.cancel', $booking))->assertRedirect('/panel/logowanie');

        $helper = User::factory()->helper()->create();
        $this->actingAs($helper)->get(route('admin.workshops.dates.bookings', $date))->assertForbidden();
        $this->actingAs($helper)->post(route('admin.workshops.bookings.cancel', $booking))->assertForbidden();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_the_list_shows_who_is_coming_with_the_contact_and_the_calendar_links_to_it(): void
    {
        $date = $this->date(seats: 5);
        $this->book($date, ['seats' => 2, 'expected_total' => 44000]);
        $this->book($date, ['name' => 'Ola Kowalska', 'email' => 'ola@example.com', 'blik_code' => '000000']);

        $this->actingAs($this->owner)->get(route('admin.workshops.edit'))
            ->assertSee(route('admin.workshops.dates.bookings', $date))
            ->assertSee('Zapisy · zajęte 2');

        $this->get(route('admin.workshops.dates.bookings', $date))
            ->assertOk()
            ->assertSeeInOrder(['Lepienie kubków', 'Zajęte 2 z 5', '3 wolne miejsca', 'Anna Nowak', '2 osoby', 'ania@example.com', '600100200', Order::query()->oldest('id')->first()->number, '440 zł', 'Potwierdzony'])
            ->assertSee('Wyślij klientce maila o anulowaniu i zwrocie')
            ->assertSeeInOrder(['Anulowane i niezapłacone (1)', 'Ola Kowalska']);
    }

    public function test_a_date_without_bookings_says_so(): void
    {
        $this->actingAs($this->owner)
            ->get(route('admin.workshops.dates.bookings', $this->date(seats: 3)))
            ->assertOk()
            ->assertSee('Nikt się jeszcze nie zapisał')
            ->assertDontSee('Anulowane i niezapłacone');
    }

    public function test_cancelling_a_paid_booking_frees_the_seat_leaves_the_refund_to_kasia_and_tells_her(): void
    {
        $date = $this->date(seats: 1);
        $booking = $this->book($date);
        $this->assertSame(0, $date->seatsLeft());

        $this->actingAs($this->owner)
            ->post(route('admin.workshops.bookings.cancel', $booking), ['notify' => '1'])
            ->assertRedirect(route('admin.workshops.dates.bookings', $date))
            ->assertSessionHas('panel_status', 'Zapis anulowany, miejsce wolne. Zamówienie '.$booking->order->number.' czeka na zwrot wpłaty w „Problem”.');

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame(1, $date->fresh()->seatsLeft());

        $order = $booking->order->fresh();
        $this->assertSame(OrderStatus::Problem, $order->status);
        $this->assertSame('Zapis na warsztat anulowany w panelu. Zwróć wpłatę 220 zł, potem zmień status na „Zakończone”.', $order->problem_note);

        Mail::assertQueued(BookingCancelled::class, function (BookingCancelled $mail) {
            $mail->assertHasSubject('Zapis na warsztat anulowany · sob. 31 października, 11:00');
            $mail->assertSeeInOrderInHtml(['Twój zapis jest anulowany', 'sob. 31 października, 11:00 – 13:00', '220 zł', 'najpóźniej w ciągu 14 dni']);
            $mail->assertSeeInText('odpisz na tego maila');

            return $mail->hasTo('ania@example.com');
        });

        // The freed seat can be booked again, and the cancelled booking gets no reminder.
        $this->post(route('workshops.booking.store', $date), $this->form(['email' => 'druga@example.com']))->assertRedirect(route('workshops.booking.confirmation'));
        $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00', 'Europe/Warsaw'));
        $this->assertSame(1, app(SendReminders::class)());
        $this->assertNull($booking->fresh()->reminded_at);
    }

    public function test_kasia_can_cancel_without_the_mail(): void
    {
        $booking = $this->book($this->date(seats: 2));

        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.cancel', $booking));

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        Mail::assertNotQueued(BookingCancelled::class);
    }

    public function test_cancelling_an_unpaid_booking_frees_the_seat_and_a_late_payment_is_flagged_for_a_refund(): void
    {
        $date = $this->date(seats: 1);
        $booking = $this->book($date, ['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]);
        $this->assertSame(BookingStatus::Held, $booking->status);

        $this->actingAs($this->owner)
            ->post(route('admin.workshops.bookings.cancel', $booking), ['notify' => '1'])
            ->assertSessionHas('panel_status', 'Zapis anulowany, miejsce wolne.');

        $this->assertSame(1, $date->fresh()->seatsLeft());
        $this->assertSame(OrderStatus::New, $booking->order->fresh()->status);
        Mail::assertNotQueued(BookingCancelled::class);

        $order = app(MarkOrderPaid::class)($booking->order, 'test-late');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(OrderStatus::Problem, $order->status);
        $this->assertSame('Płatność za zapis na warsztat, który anulowałaś w panelu. Zwróć wpłatę 220 zł.', $order->problem_note);
        $this->assertSame(1, $date->fresh()->seatsLeft());
        Mail::assertSent(Alert::class, fn (Alert $alert) => str_contains($alert->envelope()->subject, 'Płatność za anulowany zapis'));
    }

    public function test_a_booking_already_out_of_the_count_is_left_alone(): void
    {
        $booking = $this->book($this->date(seats: 2));
        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.cancel', $booking));
        $cancelledAt = $booking->fresh()->cancelled_at;

        $this->travel(5)->minutes();
        $this->post(route('admin.workshops.bookings.cancel', $booking), ['notify' => '1'])
            ->assertSessionHas('panel_status', 'Ten zapis był już zwolniony — nic nie zmieniam.');

        $this->assertEquals($cancelledAt, $booking->fresh()->cancelled_at);
        Mail::assertNotQueued(BookingCancelled::class);
    }

    public function test_a_date_with_only_cancelled_bookings_can_be_removed_and_the_bookings_stay(): void
    {
        $date = $this->date(seats: 2);
        $booking = $this->book($date);

        $this->actingAs($this->owner)->delete(route('admin.workshops.dates.destroy', $date))
            ->assertSessionHas('panel_status', 'Na ten termin są zapisy — anuluj je w „Zapisy”, zanim usuniesz termin');
        $this->assertModelExists($date);

        $this->post(route('admin.workshops.bookings.cancel', $booking));
        $this->delete(route('admin.workshops.dates.destroy', $date))->assertSessionHas('panel_status', 'Termin usunięty z terminarza');

        $this->assertModelMissing($date);
        $booking->refresh();
        $this->assertNull($booking->workshop_date_id);
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertTrue($booking->order->workshopBooking->is($booking));

        // Cancelling it again does nothing.
        $this->post(route('admin.workshops.bookings.cancel', $booking))
            ->assertSessionHas('panel_status', 'Ten zapis był już zwolniony — nic nie zmieniam.');
    }

    public function test_a_late_payment_for_a_cancelled_booking_whose_date_is_gone_is_flagged_for_a_refund(): void
    {
        $date = $this->date(seats: 1);
        $booking = $this->book($date, ['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]);

        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.cancel', $booking));
        $this->delete(route('admin.workshops.dates.destroy', $date));
        $this->assertModelMissing($date);

        $order = app(MarkOrderPaid::class)($booking->order, 'test-late');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(OrderStatus::Problem, $order->status);
        $this->assertSame('Płatność za zapis na warsztat, który anulowałaś w panelu. Zwróć wpłatę 220 zł.', $order->problem_note);
        Mail::assertSent(Alert::class, fn (Alert $alert) => str_contains($alert->envelope()->subject, 'Płatność za anulowany zapis'));
        // Not the shop's order mails: the order still books a workshop.
        Mail::assertNotQueued(OrderConfirmed::class);
        Mail::assertNotQueued(NewOrderReceived::class);

        // Her browser coming back from the payment lands on the workshops page, not on an error.
        $this->withSession(['checkout.order' => $order->number])->get(route('workshops.booking.confirmation'))
            ->assertRedirect(route('workshops.index'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(WorkshopDate $date, array $overrides = []): WorkshopBooking
    {
        $this->flushSession();
        $this->post(route('workshops.booking.store', $date), $this->form($overrides));

        return WorkshopBooking::query()->latest('id')->firstOrFail();
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
