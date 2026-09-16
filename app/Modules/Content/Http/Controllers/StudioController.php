<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\SitePhoto;
use Illuminate\View\View;

/**
 * /pracownia: the studio in Kasia's words, the facts and the photos from the panel. The address never
 * shows here — it goes only into the e-mail after a workshop booking.
 */
class StudioController extends Controller
{
    public function __invoke(Settings $settings): View
    {
        return view('content::studio', [
            'hero' => SitePhoto::url($settings->get('studio_hero_image')),
            'lead' => $settings->get('text_studio_lead'),
            'paragraphs' => array_filter([$settings->get('text_studio_paragraph_1'), $settings->get('text_studio_paragraph_2')]),
            'facts' => collect((array) $settings->get('studio_facts', []))
                ->filter(fn (mixed $fact) => is_array($fact) && filled($fact['title'] ?? null))
                ->values(),
            'gallery' => collect((array) $settings->get('studio_gallery', []))
                ->filter(fn (mixed $photo) => is_array($photo))
                ->map(fn (array $photo) => ['url' => SitePhoto::url($photo['path'] ?? null), 'alt' => (string) ($photo['alt'] ?? '')])
                ->reject(fn (array $photo) => $photo['url'] === null)
                ->values(),
        ]);
    }
}
