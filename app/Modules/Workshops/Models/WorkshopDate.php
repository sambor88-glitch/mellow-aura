<?php

namespace App\Modules\Workshops\Models;

use App\Modules\Workshops\Database\Factories\WorkshopDateFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A date on the workshop calendar: which workshop from the price list, when, and how many seats.
 * Bookings take the seats (WorkshopBooking).
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
     * The seats taken right now, summed in the same query as the dates, so a list of dates asks once.
     *
     * @param  Builder<WorkshopDate>  $query
     */
    #[Scope]
    protected function withTakenSeats(Builder $query): void
    {
        $query->withSum(['bookings as seats_taken' => fn (Builder $query) => $query->takingSeats()], 'seats');
    }

    /**
     * @return HasMany<WorkshopBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(WorkshopBooking::class);
    }

    /**
     * Seats paid for or held for a payment that may still come. Read from withTakenSeats() when the list asked
     * for it; PlaceBooking and ConfirmBooking ask afresh under a lock.
     */
    public function seatsTaken(): int
    {
        return array_key_exists('seats_taken', $this->attributes)
            ? (int) $this->attributes['seats_taken']
            : (int) $this->bookings()->takingSeats()->sum('seats');
    }

    /**
     * Seats still free on this date. The page, the structured data and the booking form read free seats only from here.
     */
    public function seatsLeft(): int
    {
        return max(0, (int) $this->seats - $this->seatsTaken());
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
