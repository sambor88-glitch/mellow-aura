<?php

namespace App\Modules\Workshops\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One move of a booking to another date (Actions\MoveBooking), for its history in the panel. Both dates are copied,
 * so the line still reads after Kasia deletes either of them.
 *
 * @property CarbonImmutable $from_starts_at
 * @property ?CarbonImmutable $from_ends_at
 * @property CarbonImmutable $to_starts_at
 * @property ?CarbonImmutable $to_ends_at
 * @property CarbonImmutable $created_at when Kasia moved it
 */
#[Fillable(['workshop_booking_id', 'from_workshop_date_id', 'to_workshop_date_id', 'from_starts_at', 'from_ends_at', 'to_starts_at', 'to_ends_at'])]
class WorkshopBookingMove extends Model
{
    /** „sob. 31.10, 11:00 – 13:00”. */
    public function fromLabel(): string
    {
        return self::label($this->from_starts_at, $this->from_ends_at);
    }

    public function toLabel(): string
    {
        return self::label($this->to_starts_at, $this->to_ends_at);
    }

    private static function label(CarbonImmutable $starts, ?CarbonImmutable $ends): string
    {
        return $starts->locale('pl')->isoFormat('ddd[.] D.MM, HH:mm').($ends ? ' – '.$ends->format('H:i') : '');
    }

    /**
     * @return BelongsTo<WorkshopBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(WorkshopBooking::class, 'workshop_booking_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_starts_at' => 'immutable_datetime',
            'from_ends_at' => 'immutable_datetime',
            'to_starts_at' => 'immutable_datetime',
            'to_ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
