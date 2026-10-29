<?php

namespace App\Modules\Workshops\Support;

use App\Modules\Settings\Settings;
use App\Modules\Workshops\Models\WorkshopBooking;
use Carbon\CarbonImmutable;

/**
 * „Dodaj do kalendarza” for a workshop booking (MA-268): the .ics file the booking mails carry and the confirmation
 * page offers, and the link to Google Calendar. The event keeps one UID for the life of the booking; a move raises its
 * SEQUENCE, so the calendar updates the event instead of adding a second one, and a cancellation removes it.
 * The exact studio address goes only into the file attached to a mail; the site's file and link have the description
 * of the area from the panel.
 */
class BookingCalendar
{
    public const string FILE_NAME = 'MellowAura-warsztat.ics';

    public const string TIMEZONE = 'Europe/Warsaw';

    public function __construct(private Settings $settings) {}

    /**
     * @param  bool  $withAddress  the exact studio address as the location — only for a mail
     */
    public function ics(WorkshopBooking $booking, bool $withAddress = false): string
    {
        return $this->calendar($booking, 'PUBLISH', [
            'STATUS:CONFIRMED',
            'SEQUENCE:'.$this->sequence($booking),
            ...$this->location($withAddress),
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:'.$this->escape('Jutro warsztat w MellowAurze'),
            'TRIGGER:-P1D',
            'END:VALARM',
        ]);
    }

    /**
     * The file that takes the event off her calendar: the same UID, one step past the last move.
     */
    public function cancellation(WorkshopBooking $booking): string
    {
        $organizer = $this->settings->get('contact_email');

        return $this->calendar($booking, 'CANCEL', [
            'STATUS:CANCELLED',
            'SEQUENCE:'.($this->sequence($booking) + 1),
            // iTIP wants both for a cancellation; Outlook ignores one without them.
            ...(filled($organizer) ? ['ORGANIZER;CN=MellowAura:mailto:'.$organizer] : []),
            'ATTENDEE;CN='.$this->escape((string) $booking->order->name).':mailto:'.$booking->order->email,
        ]);
    }

    /**
     * The mime type of the file, with its method, as mail clients read it.
     */
    public static function mime(string $method = 'PUBLISH'): string
    {
        return 'text/calendar; charset=utf-8; method='.$method;
    }

    /**
     * The same event as a link that opens it in Google Calendar, with the area of the studio, never its address.
     */
    public function googleUrl(WorkshopBooking $booking): string
    {
        [$starts, $ends] = $this->times($booking);

        return 'https://calendar.google.com/calendar/render?'.http_build_query(array_filter([
            'action' => 'TEMPLATE',
            'text' => $this->summary($booking),
            'dates' => $starts->utc()->format('Ymd\THis\Z').'/'.$ends->utc()->format('Ymd\THis\Z'),
            'details' => $this->description($booking),
            'location' => $this->area(),
            'ctz' => self::TIMEZONE,
        ], 'filled'), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  list<string>  $lines  what this kind of file adds to the event
     */
    private function calendar(WorkshopBooking $booking, string $method, array $lines): string
    {
        [$starts, $ends] = $this->times($booking);

        $event = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MellowAura//Warsztaty//PL',
            'CALSCALE:GREGORIAN',
            'METHOD:'.$method,
            ...$this->timezone(),
            'BEGIN:VEVENT',
            'UID:'.$this->uid($booking),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART;TZID='.self::TIMEZONE.':'.$starts->format('Ymd\THis'),
            'DTEND;TZID='.self::TIMEZONE.':'.$ends->format('Ymd\THis'),
            'SUMMARY:'.$this->escape($this->summary($booking)),
            'DESCRIPTION:'.$this->escape($this->description($booking)),
            ...$lines,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $event))."\r\n";
    }

    /**
     * One per booking, the same in every file: the order number stays with the booking through every move.
     */
    private function uid(WorkshopBooking $booking): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'mellow-aura.com';

        return 'workshop-'.strtolower($booking->order->number).'@'.$host;
    }

    /** How many times Kasia moved the booking: each move is a new version of the event. */
    private function sequence(WorkshopBooking $booking): int
    {
        return match (true) {
            $booking->relationLoaded('moves') => $booking->moves->count(),
            $booking->exists => $booking->moves()->count(),
            default => 0,
        };
    }

    /**
     * The start and the end in Warsaw time. A date without an end lasts as long as its start: no hours are made up.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function times(WorkshopBooking $booking): array
    {
        $date = $booking->workshopDate;
        $starts = $date->starts_at->setTimezone(self::TIMEZONE);

        return [$starts, ($date->ends_at ?? $date->starts_at)->setTimezone(self::TIMEZONE)];
    }

    private function summary(WorkshopBooking $booking): string
    {
        $workshop = trim((string) $booking->order->loadMissing('items')->items->sortBy('id')->first()?->product_name);

        return trim($workshop.' · MellowAura', ' ·');
    }

    private function description(WorkshopBooking $booking): string
    {
        return 'Zapis '.$booking->order->number.', liczba osób: '.$booking->seats.'.';
    }

    /**
     * @return list<string>
     */
    private function location(bool $withAddress): array
    {
        $address = $withAddress ? trim((string) $this->settings->get('studio_address')) : '';
        $location = $address !== '' ? $address : $this->area();

        return filled($location) ? ['LOCATION:'.$this->escape($location)] : [];
    }

    /** „okolice Błoń Krakowskich” — what the site says instead of the address. */
    private function area(): ?string
    {
        $area = trim((string) $this->settings->get('location_description'));

        return $area !== '' ? 'Pracownia MellowAura, '.$area : null;
    }

    /**
     * Europe/Warsaw with its summer time, so Outlook, which does not know the zone by name, shows the right hour.
     *
     * @return list<string>
     */
    private function timezone(): array
    {
        return [
            'BEGIN:VTIMEZONE',
            'TZID:'.self::TIMEZONE,
            'X-LIC-LOCATION:'.self::TIMEZONE,
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    /** Text values escape the backslash, the comma, the semicolon and line breaks (RFC 5545, 3.3.11). */
    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Lines longer than 75 bytes go on as continuation lines, never splitting a Polish letter. */
    private function fold(string $line): string
    {
        $parts = [];

        // A continuation line starts with a space, so it carries one byte less.
        while (strlen($line) > ($limit = $parts === [] ? 75 : 74)) {
            $cut = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $cut;
            $line = substr($line, strlen($cut));
        }

        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}
