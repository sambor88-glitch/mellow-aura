<?php

namespace App\Modules\Workshops\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Workshops\Actions\CancelBooking;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\WorkshopTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Who is coming to one date, with the contact to each of them, and cancelling a booking to free its seats.
 * The owner's screen only (Admin\Support\PanelAccess): it shows the customers' e-mails and phones.
 */
class WorkshopBookingController extends Controller
{
    public function index(WorkshopDate $date, WorkshopTypes $types): View
    {
        [$active, $inactive] = $date->bookings()->with(['order', 'voucher'])->orderBy('created_at')->orderBy('id')->get()
            ->partition(fn (WorkshopBooking $booking) => $booking->isActive());

        return view('workshops::admin.bookings', [
            'date' => $date,
            'workshop' => $types->all()->first(fn (array $type) => WorkshopTypes::key($type) === $date->workshop_code)['name'] ?? null,
            'active' => $active,
            'inactive' => $inactive,
        ]);
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
