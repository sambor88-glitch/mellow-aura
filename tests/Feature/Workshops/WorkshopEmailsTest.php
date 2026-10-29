<?php

namespace Tests\Feature\Workshops;

use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\SimulatePayment;
use App\Modules\Checkout\Mail\NewOrderReceived;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Mail\Alert;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Mail\BookingConfirmed;
use App\Modules\Workshops\Mail\BookingOverbooked;
use App\Modules\Workshops\Mail\BookingReminder;
use App\Modules\Workshops\Mail\NewBookingReceived;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The workshop mails (MA-138): the confirmation with the exact studio address in place of the shop's order mail,
 * Kasia's notice, and the reminder about a day before, sent once from the hourly scheduler.
 */
class WorkshopEmailsTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = 'ul. Przykładowa 1/2, 30-001 Kraków';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->setting('contact_email', 'kasia@example.com');
        $this->setting('studio_address', self::ADDRESS);
        $this->setting('workshop_what_to_bring', ['Ubranie, którego nie szkoda pobrudzić', 'Spięte włosy']);
        config(['monitoring.alert_email' => 'alerty@example.com']);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
    }

    public function test_a_paid_booking_sends_the_address_to_her_and_a_notice_to_kasia_instead_of_the_order_mails(): void
    {
        $date = $this->date(seats: 3);

        $this->post(route('workshops.booking.store', $date), $this->form(['seats' => 2, 'expected_total' => 44000]))
            ->assertRedirect(route('workshops.booking.confirmation'));

        Mail::assertNotQueued(OrderConfirmed::class);
        Mail::assertNotQueued(NewOrderReceived::class);

        Mail::assertQueued(BookingConfirmed::class, function (BookingConfirmed $mail) {
            $mail->assertHasSubject('Do zobaczenia w pracowni · sob. 31 października, 11:00');
            $mail->assertSeeInOrderInHtml(['Lepienie kubków', 'sob. 31 października, 11:00 – 13:00', 'Liczba osób: 2', '440 zł', self::ADDRESS]);
            $mail->assertSeeInText(self::ADDRESS);
            $mail->assertSeeInHtml('Dzień przed warsztatem przypomnę Ci o nim mailem.');
            $mail->assertSeeInOrderInHtml(['Co zabrać ze sobą', 'Ubranie, którego nie szkoda pobrudzić', 'Spięte włosy']);
            $mail->assertSeeInText('- Spięte włosy');

            return $mail->hasTo('ania@example.com') && $mail->hasReplyTo('kasia@example.com') && count($mail->attachments()) === 2;
        });

        Mail::assertQueued(NewBookingReceived::class, function (NewBookingReceived $mail) {
            $mail->assertHasSubject('Nowy zapis na warsztat · sob. 31 października, 11:00 · 2 os.');
            $mail->assertSeeInOrderInHtml(['Lepienie kubków', 'Liczba osób: 2', 'ostatnie miejsce', 'Anna Nowak', '600100200']);
            $mail->assertDontSeeInHtml(self::ADDRESS);

            return $mail->hasTo('kasia@example.com') && $mail->hasReplyTo('ania@example.com');
        });
    }

    public function test_the_address_never_shows_on_the_site(): void
    {
        $date = $this->date(seats: 3);

        $this->get(route('workshops.index'))->assertOk()->assertDontSee(self::ADDRESS);
        $this->get(route('workshops.booking.create', $date))->assertOk()->assertDontSee(self::ADDRESS);
        $this->post(route('workshops.booking.store', $date), $this->form());
        $this->get(route('workshops.booking.confirmation'))->assertOk()->assertDontSee(self::ADDRESS);
    }

    public function test_without_an_address_in_the_panel_the_mail_still_goes_and_kasia_hears_about_it(): void
    {
        Setting::query()->where('key', 'studio_address')->delete();
        app(Settings::class)->refresh();

        $this->post(route('workshops.booking.store', $this->date(seats: 3)), $this->form());

        Mail::assertQueued(BookingConfirmed::class, function (BookingConfirmed $mail) {
            $mail->assertSeeInHtml('Adres pracowni wyślę Ci osobnym mailem przed warsztatem.');

            return true;
        });
        Mail::assertSent(Alert::class, fn (Alert $alert) => $alert->hasTo('alerty@example.com') && str_contains($alert->envelope()->subject, 'Brak adresu pracowni'));
    }

    public function test_an_overbooked_payment_gets_the_apology_without_the_address(): void
    {
        $date = $this->date(seats: 1);
        $this->post(route('workshops.booking.store', $date), $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));
        $late = Order::sole();

        $this->travel(WorkshopBooking::HOLD_MINUTES + 1)->minutes();
        $this->flushSession();
        $this->post(route('workshops.booking.store', $date), $this->form(['email' => 'druga@example.com']));

        app(MarkOrderPaid::class)($late, 'test-late');

        Mail::assertQueued(BookingOverbooked::class, function (BookingOverbooked $mail) {
            $mail->assertSeeInHtml('Zwrócę Ci całą wpłatę');
            $mail->assertDontSeeInHtml(self::ADDRESS);

            return $mail->hasTo('ania@example.com');
        });
        Mail::assertQueued(BookingConfirmed::class, 1);
        Mail::assertQueued(BookingConfirmed::class, fn (BookingConfirmed $mail) => $mail->hasTo('druga@example.com'));
        Mail::assertQueued(NewBookingReceived::class, 1);
        Mail::assertNotQueued(OrderConfirmed::class);
    }

    public function test_the_reminder_goes_once_a_day_before_and_only_to_paid_bookings(): void
    {
        $date = $this->date(seats: 5);
        $this->post(route('workshops.booking.store', $date), $this->form());
        $this->flushSession();
        $this->post(route('workshops.booking.store', $date), $this->form(['email' => 'nieoplacone@example.com', 'blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));

        // Two days before: too early.
        $this->travelTo(CarbonImmutable::parse('2026-10-29 11:00', 'Europe/Warsaw'));
        $this->artisan('workshops:send-reminders')->assertSuccessful();
        Mail::assertNotQueued(BookingReminder::class);

        // The run a day before, and the next hour's run, which finds nothing new.
        $this->travelTo(CarbonImmutable::parse('2026-10-30 11:05', 'Europe/Warsaw'));
        $this->artisan('workshops:send-reminders')->assertSuccessful();
        $this->travel(1)->hours();
        $this->artisan('workshops:send-reminders')->assertSuccessful();

        Mail::assertQueued(BookingReminder::class, 1);
        Mail::assertQueued(BookingReminder::class, function (BookingReminder $mail) {
            $mail->assertHasSubject('Jutro warsztat · 11:00');
            $mail->assertSeeInOrderInHtml(['sob. 31 października, 11:00 – 13:00', self::ADDRESS, 'Spięte włosy']);

            return $mail->hasTo('ania@example.com');
        });
        $this->assertNotNull(WorkshopBooking::query()->where('status', BookingStatus::Confirmed)->sole()->reminded_at);
    }

    public function test_a_booking_paid_less_than_a_day_before_gets_no_reminder(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-30 20:00', 'Europe/Warsaw'));
        $this->post(route('workshops.booking.store', $this->date(seats: 3)), $this->form());

        Mail::assertQueued(BookingConfirmed::class, function (BookingConfirmed $mail) {
            $mail->assertDontSeeInHtml('przypomnę Ci');

            return true;
        });

        $this->artisan('workshops:send-reminders')->assertSuccessful();
        Mail::assertNotQueued(BookingReminder::class);
    }

    public function test_without_a_list_in_the_panel_the_mail_has_no_what_to_bring_part(): void
    {
        $this->setting('workshop_what_to_bring', []);

        $this->post(route('workshops.booking.store', $this->date(seats: 3)), $this->form());

        Mail::assertQueued(BookingConfirmed::class, function (BookingConfirmed $mail) {
            $mail->assertDontSeeInHtml('Co zabrać');
            $mail->assertDontSeeInText('CO ZABRAĆ');

            return true;
        });
    }

    public function test_the_reminder_runs_every_hour(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('workshops:send-reminders');
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        app(Settings::class)->refresh();
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
