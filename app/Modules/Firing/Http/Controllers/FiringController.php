<?php

namespace App\Modules\Firing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Firing\Support\KilnPrices;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\ServiceStructuredData;
use Illuminate\View\View;

/**
 * /wypal-ceramiki-krakow: the firing price list from the panel. Until the batch form arrives after
 * the holidays, a batch is reported on WhatsApp or through the contact form.
 */
class FiringController extends Controller
{
    public function __invoke(KilnPrices $prices, Settings $settings): View
    {
        $list = $prices->all();
        $lead = $settings->get('text_kiln_lead');

        return view('firing::index', [
            'prices' => $list,
            'lead' => $lead,
            'structuredData' => ServiceStructuredData::for('Wypał ceramiki na zlecenie w Krakowie', route('firing.index'), $lead,
                $list->map(fn (array $price) => [$price['label'], $price['price_gross'], $price['unit_label']])),
            'note' => $settings->get('text_kiln_note'),
            'phone' => $settings->get('contact_phone'),
        ]);
    }
}
