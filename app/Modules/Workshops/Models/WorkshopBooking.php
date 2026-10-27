<?php

namespace App\Modules\Workshops\Models;

use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Workshops\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seats on one workshop date, booked by one order. The order holds the price, the customer and the payment;
 * the booking holds the seats.
 *
 * @property ?int $workshop_date_id null once Kasia deleted the date of a cancelled booking
 * @property ?int $voucher_id the voucher that paid for the seats instead of money
 * @property BookingStatus $status
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $confirmed_at
 * @property ?CarbonImmutable $reminded_at
 * @property ?CarbonImmutable $cancelled_at
 */
#[Fillable(['workshop_date_id', 'order_id', 'voucher_id', 'seats', 'status', 'expires_at', 'confirmed_at', 'reminded_at', 'cancelled_at'])]
class WorkshopBooking extends Model
{
    /**
     * How long the seats wait for the payment: the BLIK code with its two minutes and a new one, or Przelewy24's
     * page. A payment that comes later still gets the seats if they are free (ConfirmBooking).
     */
    public const HOLD_MINUTES = 15;

    /** The reminder goes out this many hours before the start (Actions\SendReminders). */
    public const REMIND_HOURS_BEFORE = 24;

    /**
     * Bookings whose seats are taken right now: paid, or waiting for a payment that may still come.
     *
     * @param  Builder<WorkshopBooking>  $query
     */
    #[Scope]
    protected function takingSeats(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('status', BookingStatus::Confirmed)
            ->orWhere(fn (Builder $query) => $query->where('status', BookingStatus::Held)->where('expires_at', '>', now())));
    }

    /**
     * Whether the booking is still on the date's list in the panel: paid, held for a payment that may still come,
     * or overbooked and waiting for the refund.
     */
    public function isActive(): bool
    {
        return match ($this->status) {
            BookingStatus::Confirmed, BookingStatus::Overbooked => true,
            BookingStatus::Held => $this->expires_at->isFuture(),
            default => false,
        };
    }

    /**
     * Whether a reminder will go out: only for seats paid before the reminder time, since a booking made for
     * tomorrow already has everything in the fresh confirmation.
     */
    public function remindsDayBefore(): bool
    {
        return ($this->confirmed_at ?? now())->lte($this->workshopDate->starts_at->subHours(self::REMIND_HOURS_BEFORE));
    }

    /**
     * @return BelongsTo<WorkshopDate, $this>
     */
    public function workshopDate(): BelongsTo
    {
        return $this->belongsTo(WorkshopDate::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'status' => BookingStatus::class,
            'expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * The voucher that paid for the seats instead of money.
     *
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
