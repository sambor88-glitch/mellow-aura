<?php

namespace App\Modules\Workshops\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Workshops\Actions\CancelBooking;
use App\Modules\Workshops\Actions\MoveBooking;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Exceptions\BookingNotMoved;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\WorkshopTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Who is coming to one date, with the contact to each of them, cancelling a booking to free its seats, and moving
 * a paid one to another date of the same workshop (MA-210).
 * The owner's screen only (Admin\Support\PanelAccess): it shows the customers' e-mails and phones.
 */
class WorkshopBookingController extends Controller
{
    public function index(WorkshopDate $date, WorkshopTypes $types, MoveBooking $moveBooking): View
    {
        [$active, $inactive] = $date->bookings()->with(['order.items', 'voucher', 'moves'])->orderBy('created_at')->orderBy('id')->get()
            ->each(fn (WorkshopBooking $booking) => $booking->setRelation('workshopDate', $date))
            ->partition(fn (WorkshopBooking $booking) => $booking->isActive());

        // Where each paid booking can go, or why it cannot. The dates are the same for every booking of this date.
        $dates = $active->contains(fn (WorkshopBooking $booking) => $booking->status === BookingStatus::Confirmed)
            ? $moveBooking->dates($active->first())
            : collect();
        $moves = $active->mapWithKeys(fn (WorkshopBooking $booking) => [$booking->id => $booking->status === BookingStatus::Confirmed
            ? ['refusal' => $moveBooking->refusal($booking), 'dates' => $dates]
            : null]);

        return view('workshops::admin.bookings', [
            'date' => $date,
            'workshop' => $types->all()->first(fn (array $type) => WorkshopTypes::key($type) === $date->workshop_code)['name'] ?? null,
            'active' => $active,
            'inactive' => $inactive,
            'moves' => $moves,
        ]);
    }

    public function move(Request $request, WorkshopBooking $booking, MoveBooking $moveBooking): RedirectResponse
    {
        $from = $booking->workshop_date_id;
        $to = $request->filled('workshop_date_id') ? WorkshopDate::query()->find($request->integer('workshop_date_id')) : null;

        try {
            if ($to === null) {
                throw new BookingNotMoved($request->filled('workshop_date_id') ? 'Tego terminu już nie ma w terminarzu.' : 'Wybierz nowy termin.');
            }

            $moveBooking($booking, $to, $request->boolean('notify'));
        } catch (BookingNotMoved $refused) {
            $back = $from ? to_route('admin.workshops.dates.bookings', $from) : to_route('admin.workshops.edit');

            return $back->withFragment('zapis-'.$booking->id)->withErrors(['workshop_date_id' => $refused->getMessage()], 'move'.$booking->id);
        }

        return to_route('admin.workshops.dates.bookings', $to)->withFragment('zapis-'.$booking->id)
            ->with('panel_status', 'Zapis '.$booking->order->number.' przeniesiony na '.$to->dayLabel().', '.$to->timeLabel().'. Miejsce na starym terminie jest wolne.'
                .($request->boolean('notify') ? ' Klient dostanie maila z nowym terminem.' : ''));
    }

    public function cancel(Request $request, WorkshopBooking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        $paid = $booking->status->isPaid();
        $cancelled = $cancelBooking($booking, $request->boolean('notify'));

        // A booking whose date was deleted has no list to go back to.
        $back = $booking->workshop_date_id ? to_route('admin.workshops.dates.bookings', $booking->workshop_date_id) : to_route('admin.workshops.edit');

        return $back->with('panel_status', match (true) {
            ! $cancelled => 'Ten zapis był już zwolniony — nic nie zmieniam.',
            $booking->voucher_id !== null => 'Zapis anulowany, miejsce wolne. Voucher '.$booking->voucher?->code.' znów można wykorzystać.',
            $paid => 'Zapis anulowany, miejsce wolne. Zamówienie '.$booking->order->number.' czeka na zwrot wpłaty w „Problem”.',
            default => 'Zapis anulowany, miejsce wolne.',
        });
    }
}
