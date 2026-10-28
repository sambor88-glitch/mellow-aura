<?php

namespace App\Modules\Workshops\Actions;

use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Exceptions\BookingNotMoved;
use App\Modules\Workshops\Mail\BookingMoved;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\WorkshopTypes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Kasia moves a paid booking to another date of the same workshop (MA-210). Nothing about the payment changes: the order,
 * the money, the workshop voucher and what an amount voucher paid stay as they are. So the new date must cost what she
 * paid per seat (decision of 28.10) — no extra payment and no refund of a difference; a booking paid with a workshop
 * voucher moves to any date of its workshop, since the voucher books the workshop, not a price.
 *
 * Both dates are locked in the order of their ids, so a booking or a payment on either of them at the same moment waits
 * and counts the seats after the move, and two moves in opposite directions never lock each other out. The seat on the
 * old date is free at once; the reminder goes out again before the new date, and the order keeps its line as bought —
 * the move stays in the booking's history.
 */
class MoveBooking
{
    public function __construct(private WorkshopTypes $types) {}

    /**
     * Why the booking cannot be moved at all, in Kasia's words, or null when it can.
     */
    public function refusal(WorkshopBooking $booking): ?string
    {
        return match (true) {
            $booking->status !== BookingStatus::Confirmed => 'Przenieść można tylko opłacony zapis.',
            $booking->workshopDate === null || ! $booking->workshopDate->starts_at->isFuture() => 'Ten termin już się odbył — zapisu nie da się przenieść.',
            $booking->voucher_id === null && $this->currentPrice($booking) === null => 'Tego warsztatu nie ma już w cenniku, więc nie sprawdzę, czy nowy termin ma tę samą cenę.',
            ! $this->samePrice($booking) => 'Warsztat kosztuje teraz '.$booking->order->money((int) $this->currentPrice($booking))
                .' za osobę, a ten zapis był za '.$booking->order->money($this->paidPrice($booking))
                .'. Przenoszę tylko na termin w tej samej cenie — bez dopłat i zwrotów różnicy.',
            default => null,
        };
    }

    /**
     * The upcoming dates of the same workshop, with their taken seats, the booking's own date left out.
     * A date with fewer free seats than the booking needs stays on the list; the panel shows it as full.
     *
     * @return Collection<int, WorkshopDate>
     */
    public function dates(WorkshopBooking $booking): Collection
    {
        return WorkshopDate::query()
            ->upcoming()
            ->withTakenSeats()
            ->where('workshop_code', $booking->workshopDate?->workshop_code)
            ->whereKeyNot($booking->workshop_date_id)
            ->get();
    }

    /**
     * @param  bool  $notify  send her the mail with the new date and the studio address
     *
     * @throws BookingNotMoved
     */
    public function __invoke(WorkshopBooking $booking, WorkshopDate $to, bool $notify = true): WorkshopBooking
    {
        return DB::transaction(function () use ($booking, $to, $notify) {
            $current = WorkshopBooking::query()->findOrFail($booking->id);

            if ($current->workshop_date_id === null) {
                throw new BookingNotMoved('Przenieść można tylko opłacony zapis.');
            }

            if ($current->workshop_date_id === $to->id) {
                throw new BookingNotMoved('Zapis już jest na tym terminie.');
            }

            // Both dates at once, the lower id first: the same order every other move takes.
            $dates = WorkshopDate::query()->whereKey([$current->workshop_date_id, $to->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $from = $dates->get($current->workshop_date_id);
            $to = $dates->get($to->id) ?? throw new BookingNotMoved('Tego terminu już nie ma w terminarzu.');

            // Read again under the locks: a cancellation or a payment may have changed it meanwhile.
            $booking = WorkshopBooking::query()->with(['order.items', 'workshopDate'])->findOrFail($booking->id);

            if ($booking->workshop_date_id !== $from->id) {
                throw new BookingNotMoved('Ten zapis zmienił się przed chwilą — odśwież stronę i spróbuj jeszcze raz.');
            }

            if (($refusal = $this->refusal($booking)) !== null) {
                throw new BookingNotMoved($refusal);
            }

            if ($to->workshop_code !== $from->workshop_code) {
                throw new BookingNotMoved('Przenieść można tylko na termin tego samego warsztatu.');
            }

            if (! $to->starts_at->isFuture()) {
                throw new BookingNotMoved('Ten termin już się zaczął — wybierz inny.');
            }

            $takenOnNew = (int) $to->bookings()->takingSeats()->sum('seats');

            if ($to->seats - $takenOnNew < $booking->seats) {
                throw new BookingNotMoved('Na '.$to->dayLabel().', '.$to->timeLabel().' nie ma już '.($booking->seats === 1 ? 'wolnego miejsca' : $booking->seats.' wolnych miejsc').'.');
            }

            $booking->moves()->create([
                'from_workshop_date_id' => $from->id,
                'to_workshop_date_id' => $to->id,
                'from_starts_at' => $from->starts_at,
                'from_ends_at' => $from->ends_at,
                'to_starts_at' => $to->starts_at,
                'to_ends_at' => $to->ends_at,
            ]);
            $booking->update(['workshop_date_id' => $to->id, 'reminded_at' => null, 'moved_at' => now()]);
            $booking->setRelation('workshopDate', $to);

            if ($notify) {
                $previous = $from->dayLabel().', '.$from->timeLabel();
                DB::afterCommit(fn () => rescue(fn () => Mail::to(new Address($booking->order->email, $booking->order->name))
                    ->send(new BookingMoved($booking, $previous))));
            }

            return $booking;
        });
    }

    private function samePrice(WorkshopBooking $booking): bool
    {
        // A workshop voucher books the workshop on any of its dates.
        return $booking->voucher_id !== null || $this->currentPrice($booking) === $this->paidPrice($booking);
    }

    /** The price per seat the date's workshop has in the price list now, or null when it left the list. */
    private function currentPrice(WorkshopBooking $booking): ?int
    {
        $code = $booking->workshopDate?->workshop_code;

        return $this->types->all()->first(fn (array $type) => WorkshopTypes::key($type) === $code)['price_gross'] ?? null;
    }

    /** The price per seat she paid, from the order's own copy. */
    private function paidPrice(WorkshopBooking $booking): int
    {
        return (int) $booking->order->items->sortBy('id')->first()?->unit_price_gross;
    }
}
