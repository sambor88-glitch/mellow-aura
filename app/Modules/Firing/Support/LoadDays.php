<?php

namespace App\Modules\Firing\Support;

use App\Modules\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Kasia collects batches once a week, on the day set in the panel (kiln_load_weekday, ISO: 1 is Monday).
 * The form offers the next few such days, from tomorrow. No day in the panel — no dates in the form.
 */
class LoadDays
{
    public const SETTING = 'kiln_load_weekday';

    private const WEEKS = 4;

    public function __construct(private Settings $settings) {}

    public function weekday(): ?int
    {
        $day = $this->settings->get(self::SETTING);

        return is_numeric($day) && $day >= 1 && $day <= 7 ? (int) $day : null;
    }

    /**
     * Y-m-d => „czwartek, 29 października”.
     *
     * @return Collection<string, string>
     */
    public function upcoming(): Collection
    {
        if (($weekday = $this->weekday()) === null) {
            return collect();
        }

        $day = Carbon::tomorrow();

        while ($day->dayOfWeekIso !== $weekday) {
            $day->addDay();
        }

        return collect(range(0, self::WEEKS - 1))
            ->map(fn (int $week) => $day->copy()->addWeeks($week))
            ->mapWithKeys(fn (Carbon $date) => [$date->toDateString() => $date->locale('pl')->translatedFormat('l, j F')]);
    }
}
