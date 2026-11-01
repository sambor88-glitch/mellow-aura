<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Support\PagePhotos;
use App\Modules\Content\Support\PagePhotoSizes;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

/**
 * /pracownia: the studio in Kasia's words, the facts and the photos from the panel (Zdjęcia stron → Pracownia, MA-349).
 * The address never shows here — it goes only into the e-mail after a workshop booking.
 */
class StudioController extends Controller
{
    public function __invoke(Settings $settings, PagePhotos $photos, PagePhotoSizes $sizes): View
    {
        $hero = $photos->get('studio-hero');

        return view('content::studio', [
            'hero' => $hero->exists() ? $hero : null,
            'heroSrcset' => $hero->srcset(),
            'heroSize' => $hero->exists() ? $sizes->dimensions((string) $hero->path) : null,
            'lead' => $settings->get('text_studio_lead'),
            'paragraphs' => array_filter([$settings->get('text_studio_paragraph_1'), $settings->get('text_studio_paragraph_2')]),
            'facts' => collect((array) $settings->get('studio_facts', []))
                ->filter(fn (mixed $fact) => is_array($fact) && filled($fact['title'] ?? null))
                ->values(),
            'gallery' => collect($photos->series('studio-gallery-')),
        ]);
    }
}
