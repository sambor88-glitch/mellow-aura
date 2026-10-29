<?php

namespace Tests\Feature\Workshops;

use App\Models\User;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Mail\BookingCancelled;
use App\Modules\Workshops\Mail\BookingConfirmed;
use App\Modules\Workshops\Mail\BookingMoved;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\BookingCalendar;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * „Dodaj do kalendarza” (MA-268): the .ics file attached to the confirmation, the move and the cancellation — one
 * event that the calendar adds, moves and removes — and the file and the Google link on the confirmation page,
 * which never carry the exact studio address.
 */
class WorkshopCalendarTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = 'ul. Przykładowa 1/2, 30-001 Kraków';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->setting('contact_email', 'kasia@example.com');
        $this->setting('studio_address', self::ADDRESS);
        $this->setting('location_description', 'okolice Błoń Krakowskich');
        config(['monitoring.alert_email' => 'alerty@example.com']);
        $this->owner = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
    }

    public function test_the_confirmation_moving_and_cancelling_carry_one_event_that_the_calendar_updates(): void
    {
        $from = $this->date(seats: 3);
        $to = $this->date(seats: 3, startsAt: '2026-11-07 11:00');
        $booking = $this->book($from);
        $uid = 'UID:workshop-'.strtolower($booking->order->number).'@';

        $confirmed = $this->ics(BookingConfirmed::class);
        $this->assertStringContainsString("METHOD:PUBLISH\r\n", $confirmed);
        $this->assertStringContainsString($uid, $confirmed);
        $this->assertStringContainsString("SEQUENCE:0\r\n", $confirmed);
        $this->assertStringContainsString("DTSTART;TZID=Europe/Warsaw:20261031T110000\r\n", $confirmed);
        $this->assertStringContainsString('LOCATION:ul. Przykładowa 1/2\, 30-001 Kraków', $confirmed);
        $this->assertStringContainsString('TRIGGER:-P1D', $confirmed);

        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.move', $booking), ['workshop_date_id' => $to->id, 'notify' => '1']);

        $moved = $this->ics(BookingMoved::class);
        $this->assertStringContainsString($uid, $moved);
        $this->assertStringContainsString("SEQUENCE:1\r\n", $moved);
        $this->assertStringContainsString("DTSTART;TZID=Europe/Warsaw:20261107T110000\r\n", $moved);
        $this->assertStringContainsString('LOCATION:ul. Przykładowa', $moved);

        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.cancel', $booking), ['notify' => '1']);

        $cancelled = $this->ics(BookingCancelled::class, 'CANCEL');
        $this->assertStringContainsString("METHOD:CANCEL\r\n", $cancelled);
        $this->assertStringContainsString($uid, $cancelled);
        $this->assertStringContainsString("STATUS:CANCELLED\r\nSEQUENCE:2\r\n", $cancelled);
        $this->assertStringContainsString('ATTENDEE;CN=Anna Nowak:mailto:ania@example.com', $cancelled);
    }

    public function test_the_mails_say_what_the_file_does(): void
    {
        $from = $this->date(seats: 3);
        $to = $this->date(seats: 3, startsAt: '2026-11-07 11:00');
        $booking = $this->book($from);

        Mail::assertQueued(BookingConfirmed::class, function (BookingConfirmed $mail) {
            $mail->assertSeeInHtml('warsztat do dodania w Twoim kalendarzu');
            $mail->assertSeeInText('warsztat do dodania w Twoim kalendarzu');
            $mail->assertHasAttachment(Attachment::fromData(fn () => '', 'MellowAura-regulamin.pdf'));

            return true;
        });

        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.move', $booking), ['workshop_date_id' => $to->id, 'notify' => '1']);
        Mail::assertQueued(BookingMoved::class, function (BookingMoved $mail) {
            $mail->assertSeeInHtml('przesunie się sam');
            $mail->assertSeeInText('przesunie się sam');

            return true;
        });

        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.cancel', $booking), ['notify' => '1']);
        Mail::assertQueued(BookingCancelled::class, function (BookingCancelled $mail) {
            $mail->assertSeeInHtml('usunie warsztat z Twojego kalendarza');
            $mail->assertSeeInText('usunie warsztat z Twojego kalendarza');

            return true;
        });
    }

    public function test_the_confirmation_page_offers_the_file_and_google_without_the_address(): void
    {
        $this->book($this->date(seats: 3));

        $page = $this->get(route('workshops.booking.confirmation'))
            ->assertOk()
            ->assertSee('Dodaj do kalendarza')
            ->assertSee(route('workshops.booking.calendar'))
            ->assertSee('https://calendar.google.com/calendar/render?action=TEMPLATE', false)
            ->getContent();
        $this->assertStringNotContainsString('Przykładowa', urldecode(html_entity_decode($page)));

        $response = $this->get(route('workshops.booking.calendar'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8; method=PUBLISH')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.BookingCalendar::FILE_NAME.'"')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $ics = $response->getContent();
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('LOCATION:Pracownia MellowAura\, okolice Błoń Krakowskich', $ics);
        $this->assertStringNotContainsString('Przykładowa', $ics);
    }

    public function test_no_file_without_a_paid_booking_in_the_session(): void
    {
        $this->get(route('workshops.booking.calendar'))->assertNotFound();

        $this->book($this->date(seats: 3), ['payment_method' => 'card', 'blik_code' => null]);
        Order::sole()->update(['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        WorkshopBooking::sole()->update(['status' => BookingStatus::Held, 'confirmed_at' => null]);

        $this->get(route('workshops.booking.calendar'))->assertNotFound();
        $this->get(route('workshops.booking.confirmation'))->assertOk()->assertDontSee('Dodaj do kalendarza');
    }

    /**
     * The .ics attached to the last queued mail of that class.
     *
     * @param  class-string<Mailable>  $class
     */
    private function ics(string $class, string $method = 'PUBLISH'): string
    {
        /** @var Mailable $mail */
        $mail = Mail::queued($class)->last();
        $file = collect($mail->attachments())->first(fn (Attachment $attachment) => $attachment->as === BookingCalendar::FILE_NAME);

        $this->assertNotNull($file, 'No calendar file attached to '.$class);
        $this->assertSame(BookingCalendar::mime($method), $file->mime);

        return $file->attachWith(fn () => '', fn (Closure $data) => $data());
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        app(Settings::class)->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(WorkshopDate $date, array $overrides = []): WorkshopBooking
    {
        $this->post(route('workshops.booking.store', $date), array_filter([
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'seats' => 1,
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'expected_total' => 22000,
            'accept_terms' => '1',
            ...$overrides,
        ], fn (mixed $value) => $value !== null));

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
}
