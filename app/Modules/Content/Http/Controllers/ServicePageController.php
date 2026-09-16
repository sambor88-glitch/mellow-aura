<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Enums\Service;
use App\Modules\Content\Models\ServiceExample;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

/**
 * /z-twojej-apaszki and /odcisk-twojej-rosliny: the sentences, steps and prices from the panel and the
 * „przed i po” pairs Kasia uploads. An order is agreed through the contact form or WhatsApp.
 */
class ServicePageController extends Controller
{
    public function scarf(Settings $settings): View
    {
        return $this->show(Service::Scarf, $settings);
    }

    public function imprint(Settings $settings): View
    {
        return $this->show(Service::Imprint, $settings);
    }

    private function show(Service $service, Settings $settings): View
    {
        return view('content::services.show', [
            'service' => $service,
            'heading' => $settings->get($service->setting('heading')),
            'lead' => $settings->get($service->setting('lead')),
            'lead2' => $settings->get($service->setting('lead_2')),
            'note' => $settings->get($service->setting('note')),
            // No crossed-out price: a price before a promotion would need the lowest price from 30 days, which services don't track yet.
            'prices' => collect((array) $settings->get($service->setting('prices'), []))
                ->filter(fn (mixed $row) => is_array($row) && filled($row['label'] ?? null) && is_numeric($row['price_gross'] ?? null))
                ->values(),
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
