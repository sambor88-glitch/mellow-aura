<?php

namespace App\Modules\Workshops\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Http\Requests\Admin\StoreWorkshopDateRequest;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Http\RedirectResponse;

/**
 * The workshop calendar in the panel: add a date, remove a date nobody is booked on.
 */
class WorkshopDateController extends Controller
{
    public function store(StoreWorkshopDateRequest $request): RedirectResponse
    {
        WorkshopDate::query()->create($request->workshopDate());

        return to_route('admin.workshops.edit')->withFragment('terminy')->with('panel_status', 'Termin dodany do terminarza');
    }

    public function destroy(WorkshopDate $date): RedirectResponse
    {
        // Someone paid for or is paying for a seat; the date stays until each booking is cancelled (WorkshopBookingController).
        if ($date->bookings()->where('status', '!=', BookingStatus::Cancelled)->exists()) {
            return to_route('admin.workshops.edit')->withFragment('terminy')
                ->with('panel_status', 'Na ten termin są zapisy — anuluj je w „Zapisy”, zanim usuniesz termin');
        }

        // Cancelled bookings stay without their date, so a payment that still comes for one is flagged for a refund.
        $date->delete();

        return to_route('admin.workshops.edit')->withFragment('terminy')->with('panel_status', 'Termin usunięty z terminarza');
    }
}
