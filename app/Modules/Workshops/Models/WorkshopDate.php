<?php

namespace App\Modules\Workshops\Models;

use App\Modules\Workshops\Database\Factories\WorkshopDateFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A date on the workshop calendar: which workshop from the price list, when, and how many seats.
 * Bookings against the seats come with MA-136.
 *
 * @property CarbonImmutable $starts_at
 * @property ?CarbonImmutable $ends_at
 */
#[Fillable(['workshop_code', 'starts_at', 'ends_at', 'seats'])]
class WorkshopDate extends Model
{
    /** @use HasFactory<WorkshopDateFactory> */
    use HasFactory;

    /**
     * Dates that have not started yet, the nearest first. Past dates stay in the table for the history of bookings.
     *
     * @param  Builder<WorkshopDate>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('starts_at', '>', now())->orderBy('starts_at')->orderBy('id');
    }

    /**
     * Seats still free on this date. Until bookings arrive with MA-136 every seat is free;
     * the page and the structured data read free seats only from here.
     */
    public function seatsLeft(): int
    {
        return max(0, (int) $this->seats);
    }

    /** „ostatnie miejsce”, „3 wolne miejsca”, „5 wolnych miejsc”. */
    public function seatsLeftLabel(): string
    {
        $left = $this->seatsLeft();

        return match (true) {
            $left === 0 => 'brak miejsc',
            $left === 1 => 'ostatnie miejsce',
            in_array($left % 10, [2, 3, 4], true) && ! in_array($left % 100, [12, 13, 14], true) => $left.' wolne miejsca',
            default => $left.' wolnych miejsc',
        };
    }

    /** „sob. 31 października”. */
    public function dayLabel(): string
    {
        return $this->starts_at->locale('pl')->isoFormat('ddd[.] D MMMM');
    }

    /** „11:00 – 13:30”, or the start alone when the end is not set. */
    public function timeLabel(): string
    {
        return $this->starts_at->format('H:i').($this->ends_at ? ' – '.$this->ends_at->format('H:i') : '');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'seats' => 'integer',
        ];
    }
}
