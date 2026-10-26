<?php

namespace App\Modules\Workshops\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Workshops\Http\Requests\Admin\StoreWorkshopDateRequest;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Http\RedirectResponse;

/**
 * The workshop calendar in the panel: add a date, remove a date.
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
        $date->delete();

        return to_route('admin.workshops.edit')->withFragment('terminy')->with('panel_status', 'Termin usunięty z terminarza');
    }
}
