<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

/**
 * /wysylka-i-pielegnacja: questions and answers from the panel, how Kasia packs and ships, and the delivery prices
 * the terms point to.
 */
class FaqController extends Controller
{
    public function __invoke(Settings $settings, ShippingMethods $shipping): View
    {
        return view('content::faq', [
            'questions' => collect((array) $settings->get('faq_items', []))
                ->filter(fn (mixed $item) => is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null))
                ->values(),
            // One step per line, in the language of the page; Polish never stands in for a missing English text.
            'packingSteps' => array_values(array_filter(array_map('trim', explode("\n", (string) $settings->get(
                Locales::current() === Locales::default() ? 'text_packing_steps' : 'text_packing_steps_'.Locales::current(),
            ))))),
            'shippingMethods' => $shipping->all(),
            'freeFrom' => $shipping->freeFrom(),
            'returnAddress' => $settings->get('return_address'),
            'phone' => $settings->get('contact_phone'),
        ]);
    }
}
