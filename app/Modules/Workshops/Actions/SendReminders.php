<?php

namespace App\Modules\Workshops\Actions;

use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Mail\BookingReminder;
use App\Modules\Workshops\Models\WorkshopBooking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Queues the reminder for every paid booking whose workshop starts within WorkshopBooking::REMIND_HOURS_BEFORE hours.
 * The scheduler runs it every hour, so an hour the server missed is caught up by the next run. Each booking is marked
 * before its mail is queued and the mark is taken only by the run that sets it, so two runs at once never send it twice.
 * A booking paid after the reminder time gets none (WorkshopBooking::remindsDayBefore), nor does an overbooked one.
 */
class SendReminders
{
    /**
     * @return int how many reminders were queued
     */
    public function __invoke(): int
    {
        $sent = 0;

        $due = WorkshopBooking::query()
            ->with(['order', 'workshopDate'])
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminded_at')
            ->whereHas('workshopDate', fn (Builder $query) => $query
                ->where('starts_at', '>', now())
                ->where('starts_at', '<=', now()->addHours(WorkshopBooking::REMIND_HOURS_BEFORE)))
            ->orderBy('id')
            ->get()
            ->filter(fn (WorkshopBooking $booking) => $booking->remindsDayBefore());

        foreach ($due as $booking) {
            if (WorkshopBooking::query()->whereKey($booking->id)->whereNull('reminded_at')->update(['reminded_at' => now()]) !== 1) {
                continue;
            }

            try {
                Mail::to(new Address($booking->order->email, $booking->order->name))->send(new BookingReminder($booking));
                $sent++;
            } catch (Throwable $exception) {
                // Not queued: the next run tries again.
                WorkshopBooking::query()->whereKey($booking->id)->update(['reminded_at' => null]);
                report($exception);
            }
        }

        return $sent;
    }
}
