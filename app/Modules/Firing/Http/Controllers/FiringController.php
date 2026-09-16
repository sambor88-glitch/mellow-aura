<?php

namespace App\Modules\Firing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Firing\Support\KilnPrices;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

/**
 * /wypal-ceramiki-krakow: the firing price list from the panel. Until the batch form arrives after
 * the holidays, a batch is reported on WhatsApp or through the contact form.
 */
class FiringController extends Controller
{
    public function __invoke(KilnPrices $prices, Settings $settings): View
    {
        return view('firing::index', [
            'prices' => $prices->all(),
            'lead' => $settings->get('text_kiln_lead'),
            'note' => $settings->get('text_kiln_note'),
            'phone' => $settings->get('contact_phone'),
        ]);
    }
}
