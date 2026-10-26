<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Enums\Service;
use App\Modules\Content\Models\ServiceExample;
use App\Modules\Content\Support\ServicePrices;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

/**
 * /z-twojej-apaszki and /odcisk-twojej-rosliny: the sentences, steps and prices from the panel and the
 * „przed i po” pairs Kasia uploads. An order is agreed through the contact form or WhatsApp.
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
        return view('content::services.show', [
            'service' => $service,
            'heading' => $settings->get($service->setting('heading')),
            'lead' => $settings->get($service->setting('lead')),
            'lead2' => $settings->get($service->setting('lead_2')),
            'note' => $settings->get($service->setting('note')),
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
