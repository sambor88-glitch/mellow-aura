<?php

namespace App\Modules\Workshops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\ServiceStructuredData;
use App\Modules\Workshops\Support\WorkshopTypes;
use Illuminate\View\View;

/**
 * /warsztaty-ceramiczne-krakow: the price list from the panel. Until booking online arrives after
 * the holidays, a date is agreed on WhatsApp or through the contact form.
 */
class WorkshopController extends Controller
{
    public function __invoke(WorkshopTypes $types, Settings $settings): View
    {
        $workshops = $types->all();
        $lead = $settings->get('text_workshops_lead');

        return view('workshops::index', [
            'workshops' => $workshops,
            'lead' => $lead,
            'structuredData' => ServiceStructuredData::for('Warsztaty ceramiczne w Krakowie', route('workshops.index'), $lead,
                $workshops->map(fn (array $workshop) => [$workshop['name'], $workshop['price_gross'], $workshop['unit_label']])),
            'phone' => $settings->get('contact_phone'),
            'location' => $settings->get('location_description'),
        ]);
    }
}
