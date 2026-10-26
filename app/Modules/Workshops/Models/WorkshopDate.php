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
