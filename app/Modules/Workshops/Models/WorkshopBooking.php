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
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property ?CarbonImmutable $moved_at the last time Kasia moved it to another date (MA-210)
 */
#[Fillable(['workshop_date_id', 'order_id', 'voucher_id', 'seats', 'status', 'expires_at', 'confirmed_at', 'reminded_at', 'cancelled_at', 'moved_at'])]
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
     * Whether a reminder will go out: only for seats paid (or moved here) before the reminder time, since a booking
     * made or moved for tomorrow already has everything in the fresh mail.
     */
    public function remindsDayBefore(): bool
    {
        return ($this->moved_at ?? $this->confirmed_at ?? now())->lte($this->workshopDate->starts_at->subHours(self::REMIND_HOURS_BEFORE));
    }

    /**
     * How the seats were paid, for the mails and the panel: „voucher MA-…” for a workshop voucher, otherwise what came in
     * and what amount vouchers paid (MA-244). Each amount in the order's currency.
     */
    public function paidLabel(): string
    {
        $order = $this->order;
        // An order made up for the panel's preview of the mails has no voucher part at all.
        $byVouchers = (int) $order->vouchers_gross;

        return match (true) {
            $this->voucher !== null => 'voucher '.$this->voucher->code,
            $byVouchers === 0 => 'zapłacone '.$order->money($order->total_gross),
            $byVouchers === $order->total_gross => 'zapłacone voucherem '.$order->money($byVouchers),
            default => 'zapłacone '.$order->money($order->total_gross - $byVouchers).' i voucherem '.$order->money($byVouchers),
        };
    }

    /**
     * @return BelongsTo<WorkshopDate, $this>
     */
    public function workshopDate(): BelongsTo
    {
        return $this->belongsTo(WorkshopDate::class);
    }

    /**
     * The dates it was moved between, the latest first.
     *
     * @return HasMany<WorkshopBookingMove, $this>
     */
    public function moves(): HasMany
    {
        return $this->hasMany(WorkshopBookingMove::class)->latest('id');
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
            'moved_at' => 'immutable_datetime',
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
