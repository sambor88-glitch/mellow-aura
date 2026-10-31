<?php

namespace App\Modules\Workshops\Support\Search;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Admin\Support\Search\SearchProvider;
use App\Modules\Admin\Support\Search\SearchResult;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\WorkshopTypes;

/**
 * Coming workshop dates in the panel search (MA-308), by the workshop's name and the day — „sobota”, „14 listopada”,
 * „14.11” — the nearest first. Enter opens the list of people booked for that date.
 */
final class WorkshopDateSearch implements SearchProvider
{
    /** A studio has a few dates a week; this reaches well past the coming months. */
    private const DATES = 120;

    public function group(): string
    {
        return 'Warsztaty';
    }

    public function screen(): string
    {
        return 'admin.workshops.edit';
    }

    public function search(string $term, int $limit): iterable
    {
        $names = app(WorkshopTypes::class)->all()->mapWithKeys(fn (array $type) => [WorkshopTypes::key($type) => $type['name']]);

        return WorkshopDate::query()->upcoming()->withTakenSeats()->limit(self::DATES)->get()
            ->filter(fn (WorkshopDate $date) => PanelSearch::matches(
                $term,
                $names[$date->workshop_code] ?? null,
                $date->dayLabel(),
                $date->starts_at->locale('pl')->isoFormat('dddd D MMMM'),
                $date->starts_at->format('j.m d.m j.n.Y'),
                'warsztat warsztaty termin zapisy',
            ))
            ->take($limit)
            ->map(fn (WorkshopDate $date) => new SearchResult(
                title: ($names[$date->workshop_code] ?? 'Warsztat').' · '.$date->dayLabel(),
                url: route('admin.workshops.dates.bookings', $date),
                route: 'admin.workshops.dates.bookings',
                detail: $date->timeLabel(),
                meta: $date->seatsLeftLabel(),
                icon: '◷',
            ))
            ->values();
    }
}
