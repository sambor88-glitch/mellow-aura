<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Enums\Service;
use App\Modules\Content\Models\ServiceExample;
use App\Modules\Content\Support\ServicePrices;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

/**
 * /z-twojej-apaszki and /odcisk-twojej-rosliny: the sentences, steps and prices from the panel (Usługi) and the
 * „przed i po” pairs Kasia uploads. An empty text drops out of the page. An order is agreed through the custom order
 * form or WhatsApp.
 */
class ServicePageController extends Controller
{
    public function scarf(Settings $settings, ServicePrices $prices): View
    {
        return $this->show(Service::Scarf, $settings, $prices);
    }

    public function imprint(Settings $settings, ServicePrices $prices): View
    {
        return $this->show(Service::Imprint, $settings, $prices);
    }

    private function show(Service $service, Settings $settings, ServicePrices $prices): View
    {
        $text = fn (string $name): ?string => filled($value = $settings->get($service->setting($name))) ? (string) $value : null;

        return view('content::services.show', [
            'service' => $service,
            'eyebrow' => $text('eyebrow'),
            'heading' => $text('heading'),
            'lead' => $text('lead'),
            'lead2' => $text('lead_2'),
            'cta' => $text('cta'),
            'examplesLead' => $text('examples_lead'),
            'pricesHeading' => $text('prices_heading'),
            'note' => $text('note'),
            'card' => [
                'eyebrow' => $text('card_eyebrow'),
                'heading' => $text('card_heading'),
                'list' => array_values(array_filter(array_map('trim', explode("\n", (string) $text('card_list'))), 'strlen')),
                'text' => $text('card_text'),
            ],
            'prices' => $prices->for($service),
            'steps' => collect((array) $settings->get($service->setting('steps'), []))
                ->filter(fn (mixed $step) => is_array($step) && filled($step['title'] ?? null))
                ->values(),
            'lockerCode' => $settings->get('parcel_locker_code'),
            'phone' => $settings->get('contact_phone'),
            'examples' => ServiceExample::query()
                ->where('service', $service)
                ->ordered()
                ->with('media')
                ->get()
                ->filter(fn (ServiceExample $example) => $example->isComplete())
                ->values(),
        ]);
    }
}
