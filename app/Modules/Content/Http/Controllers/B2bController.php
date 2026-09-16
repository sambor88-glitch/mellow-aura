<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

/**
 * /ceramika-dla-gastronomii: ceramics for cafés and restaurants, with the sentences and the cards from the panel.
 * A quote or a meeting is asked for through the contact form or WhatsApp.
 */
class B2bController extends Controller
{
    public function __invoke(Settings $settings): View
    {
        return view('content::b2b', [
            'lead' => $settings->get('text_b2b_lead'),
            'facts' => collect((array) $settings->get('b2b_facts', []))
                ->filter(fn (mixed $fact) => is_array($fact) && filled($fact['title'] ?? null))
                ->values(),
            'ctaHeading' => $settings->get('text_b2b_cta_heading'),
            'cta' => $settings->get('text_b2b_cta'),
            'phone' => $settings->get('contact_phone'),
        ]);
    }
}
