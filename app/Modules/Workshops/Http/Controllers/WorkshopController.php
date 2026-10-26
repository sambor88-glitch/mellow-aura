<?php

namespace App\Modules\Workshops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\ServiceStructuredData;
use App\Modules\Shared\Support\SitePhoto;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\WorkshopStructuredData;
use App\Modules\Workshops\Support\WorkshopTypes;
use Illuminate\View\View;

/**
 * /warsztaty-ceramiczne-krakow: the price list and the upcoming dates from the panel, with the studio's photos.
 * A date is booked and paid on its own form (BookingController); a question still goes to WhatsApp or the contact form.
 */
class WorkshopController extends Controller
{
    /** Studio photos shown under the dates; the rest are on /pracownia. */
    private const GALLERY_PHOTOS = 4;

    public function __invoke(WorkshopTypes $types, Settings $settings): View
    {
        $workshops = $types->all();
        $lead = $settings->get('text_workshops_lead');
        $byCode = $workshops->keyBy(fn (array $workshop) => WorkshopTypes::key($workshop));

        // A date whose workshop is no longer in the price list has no name or price to show.
        $dates = WorkshopDate::query()->upcoming()->withTakenSeats()->get()
            ->filter(fn (WorkshopDate $date) => $byCode->has($date->workshop_code))
            ->map(fn (WorkshopDate $date) => ['date' => $date, 'workshop' => $byCode->get($date->workshop_code)])
            ->values();

        return view('workshops::index', [
            'workshops' => $workshops,
            'dates' => $dates,
            'lead' => $lead,
            'structuredData' => ServiceStructuredData::for('Warsztaty ceramiczne w Krakowie', route('workshops.index'), $lead,
                $workshops->map(fn (array $workshop) => [$workshop['name'], $workshop['price_gross'], $workshop['unit_label']]))
                .WorkshopStructuredData::for(route('workshops.index'), $lead, $dates),
            'phone' => $settings->get('contact_phone'),
            'location' => $settings->get('location_description'),
            'hero' => SitePhoto::url($settings->get('studio_hero_image')),
            'gallery' => collect((array) $settings->get('studio_gallery', []))
                ->filter(fn (mixed $photo) => is_array($photo))
                ->map(fn (array $photo) => ['url' => SitePhoto::url($photo['path'] ?? null), 'alt' => (string) ($photo['alt'] ?? '')])
                ->reject(fn (array $photo) => $photo['url'] === null)
                ->take(self::GALLERY_PHOTOS)
                ->values(),
        ]);
    }
}
