<?php

namespace Tests\Unit\Workshops;

use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Settings\Settings;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopBookingMove;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\BookingCalendar;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The .ics file and the Google Calendar link of a workshop booking (MA-268): one UID per booking, SEQUENCE raised by
 * every move, METHOD:CANCEL for a cancellation, Warsaw time, a reminder a day before, and the address only when asked.
 */
class BookingCalendarTest extends TestCase
{
    private const ADDRESS = 'ul. Przykładowa 1/2, 30-001 Kraków';

    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://mellow-aura.com']);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
        $this->settings([
            'studio_address' => self::ADDRESS,
            'location_description' => 'okolice Błoń Krakowskich',
            'contact_email' => 'kasia@example.com',
        ]);
    }

    public function test_the_event_has_warsaw_time_a_stable_uid_and_a_reminder_a_day_before(): void
    {
        $ics = $this->calendar()->ics($this->booking());

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("METHOD:PUBLISH\r\n", $ics);
        $this->assertStringContainsString("BEGIN:VTIMEZONE\r\nTZID:Europe/Warsaw\r\n", $ics);
        $this->assertStringContainsString("UID:workshop-ma-2026-1042@mellow-aura.com\r\n", $ics);
        $this->assertStringContainsString("DTSTAMP:20261026T110000Z\r\n", $ics);
        $this->assertStringContainsString("DTSTART;TZID=Europe/Warsaw:20261031T110000\r\n", $ics);
        $this->assertStringContainsString("DTEND;TZID=Europe/Warsaw:20261031T133000\r\n", $ics);
        $this->assertStringContainsString("SUMMARY:Lepienie kubków · MellowAura\r\n", $ics);
        $this->assertStringContainsString("DESCRIPTION:Zapis MA-2026-1042\\, liczba osób: 2.\r\n", $ics);
        $this->assertStringContainsString("STATUS:CONFIRMED\r\nSEQUENCE:0\r\n", $ics);
        $this->assertStringContainsString("BEGIN:VALARM\r\nACTION:DISPLAY\r\nDESCRIPTION:Jutro warsztat w MellowAurze\r\nTRIGGER:-P1D\r\nEND:VALARM\r\n", $ics);
        $this->assertStringNotContainsString("\n\n", $ics);
    }

    public function test_the_exact_address_goes_in_only_for_a_mail(): void
    {
        $calendar = $this->calendar();

        $this->assertStringContainsString('LOCATION:ul. Przykładowa 1/2\, 30-001 Kraków', $calendar->ics($this->booking(), withAddress: true));

        $site = $calendar->ics($this->booking());
        $this->assertStringContainsString("LOCATION:Pracownia MellowAura\\, okolice Błoń Krakowskich\r\n", $site);
        $this->assertStringNotContainsString('Przykładowa', $site);

        // Without the address in the panel the mail's file falls back to the area; without either it has no location.
        $this->settings(['location_description' => 'okolice Błoń Krakowskich']);
        $this->assertStringContainsString('LOCATION:Pracownia MellowAura\, okolice', $this->calendar()->ics($this->booking(), withAddress: true));
        $this->settings([]);
        // X-LIC-LOCATION in the time zone is no location of the event.
        $this->assertStringNotContainsString("\r\nLOCATION:", $this->calendar()->ics($this->booking(), withAddress: true));
    }

    public function test_a_move_keeps_the_uid_and_raises_the_sequence_and_a_cancellation_goes_one_further(): void
    {
        $calendar = $this->calendar();
        $first = $calendar->ics($this->booking());
        $moved = $this->booking(moves: 2, startsAt: '2026-11-07 11:00');

        $this->assertStringContainsString('UID:workshop-ma-2026-1042@mellow-aura.com', $first);
        $this->assertStringContainsString('SEQUENCE:0', $first);

        $ics = $calendar->ics($moved);
        $this->assertStringContainsString("UID:workshop-ma-2026-1042@mellow-aura.com\r\n", $ics);
        $this->assertStringContainsString("SEQUENCE:2\r\n", $ics);
        $this->assertStringContainsString("DTSTART;TZID=Europe/Warsaw:20261107T110000\r\n", $ics);

        $cancel = $calendar->cancellation($moved);
        $this->assertStringContainsString("METHOD:CANCEL\r\n", $cancel);
        $this->assertStringContainsString("UID:workshop-ma-2026-1042@mellow-aura.com\r\n", $cancel);
        $this->assertStringContainsString("STATUS:CANCELLED\r\nSEQUENCE:3\r\n", $cancel);
        $this->assertStringContainsString("ORGANIZER;CN=MellowAura:mailto:kasia@example.com\r\n", $cancel);
        $this->assertStringContainsString("ATTENDEE;CN=Anna Nowak:mailto:ania@example.com\r\n", $cancel);
        $this->assertStringNotContainsString('VALARM', $cancel);
        $this->assertSame('text/calendar; charset=utf-8; method=CANCEL', BookingCalendar::mime('CANCEL'));
    }

    public function test_winter_time_and_a_date_without_an_end(): void
    {
        $ics = $this->calendar()->ics($this->booking(startsAt: '2026-12-05 10:00', minutes: null));

        $this->assertStringContainsString("DTSTART;TZID=Europe/Warsaw:20261205T100000\r\n", $ics);
        $this->assertStringContainsString("DTEND;TZID=Europe/Warsaw:20261205T100000\r\n", $ics);
    }

    public function test_long_lines_fold_at_75_bytes_without_splitting_a_letter(): void
    {
        $ics = $this->calendar()->ics($this->booking(workshop: str_repeat('Łączenie glin ', 12)));

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }

        $unfolded = str_replace("\r\n ", '', $ics);
        $this->assertStringContainsString('SUMMARY:'.trim(str_repeat('Łączenie glin ', 12)).' · MellowAura', $unfolded);
    }

    public function test_the_google_link_has_the_times_in_utc_and_the_area_never_the_address(): void
    {
        $url = $this->calendar()->googleUrl($this->booking());
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);
        $this->assertSame('TEMPLATE', $query['action']);
        $this->assertSame('Lepienie kubków · MellowAura', $query['text']);
        $this->assertSame('20261031T100000Z/20261031T123000Z', $query['dates']);
        $this->assertSame('Pracownia MellowAura, okolice Błoń Krakowskich', $query['location']);
        $this->assertSame('Europe/Warsaw', $query['ctz']);
        $this->assertStringNotContainsString('Przykładowa', urldecode($url));
    }

    private function calendar(): BookingCalendar
    {
        return new BookingCalendar($this->settings);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        $this->settings = new class($values) extends Settings
        {
            /** @param array<string, mixed> $values */
            public function __construct(private array $values) {}

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
        };
    }

    private function booking(int $moves = 0, string $startsAt = '2026-10-31 11:00', ?int $minutes = 150, string $workshop = 'Lepienie kubków'): WorkshopBooking
    {
        $starts = CarbonImmutable::parse($startsAt, 'Europe/Warsaw');
        $order = (new Order)->forceFill(['number' => 'MA-2026-1042', 'name' => 'Anna Nowak', 'email' => 'ania@example.com'])
            ->setRelation('items', collect([(new OrderItem)->forceFill(['id' => 1, 'product_name' => $workshop])]));

        return (new WorkshopBooking)->forceFill(['seats' => 2])
            ->setRelation('order', $order)
            ->setRelation('moves', collect(array_fill(0, $moves, new WorkshopBookingMove)))
            ->setRelation('workshopDate', (new WorkshopDate)->forceFill([
                'starts_at' => $starts,
                'ends_at' => $minutes === null ? null : $starts->addMinutes($minutes),
            ]));
    }
}
