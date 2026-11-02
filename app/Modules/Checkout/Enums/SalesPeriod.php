<?php

namespace App\Modules\Checkout\Enums;

use Carbon\CarbonImmutable;

/**
 * The periods of the sales summary (MA-344). Each one is compared with the period before it, cut to the same length:
 * this month so far against the same days of the last month, the year so far against the same part of the last year.
 * Ranges are half-open: from the first moment, up to but without the last.
 */
enum SalesPeriod: string
{
    case ThisMonth = 'month';
    case LastMonth = 'previous-month';
    case Last30Days = '30-days';
    case ThisYear = 'year';

    public function label(): string
    {
        return match ($this) {
            self::ThisMonth => 'Ten miesiąc',
            self::LastMonth => 'Poprzedni miesiąc',
            self::Last30Days => 'Ostatnie 30 dni',
            self::ThisYear => 'Ten rok',
        };
    }

    /** What the comparison under each number is against. */
    public function previousLabel(): string
    {
        return match ($this) {
            self::ThisMonth => 'te same dni poprzedniego miesiąca',
            self::LastMonth => 'miesiąc wcześniej',
            self::Last30Days => '30 dni wcześniej',
            self::ThisYear => 'ta sama część poprzedniego roku',
        };
    }

    /** A month is read day by day, a year week by week. */
    public function byWeek(): bool
    {
        return $this === self::ThisYear;
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function range(CarbonImmutable $now): array
    {
        return match ($this) {
            self::ThisMonth => [$now->startOfMonth(), $now],
            self::LastMonth => [$now->startOfMonth()->subMonthNoOverflow(), $now->startOfMonth()],
            self::Last30Days => [$now->startOfDay()->subDays(29), $now],
            self::ThisYear => [$now->startOfYear(), $now],
        };
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function previousRange(CarbonImmutable $now): array
    {
        [$from, $to] = $this->range($now);

        return match ($this) {
            self::ThisMonth => self::sameStretch($from->subMonthNoOverflow(), $from, $from->diffInSeconds($to)),
            self::LastMonth => [$from->subMonthNoOverflow(), $from],
            self::Last30Days => [$from->subDays(30), $from],
            self::ThisYear => self::sameStretch($from->subYear(), $from, $from->diffInSeconds($to)),
        };
    }

    /**
     * The same stretch of time from the start of the earlier period, never past its end: the 31st of a month
     * is compared with the whole of a shorter month before it.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private static function sameStretch(CarbonImmutable $from, CarbonImmutable $end, float $seconds): array
    {
        return [$from, $from->addSeconds((int) $seconds)->min($end)];
    }
}
