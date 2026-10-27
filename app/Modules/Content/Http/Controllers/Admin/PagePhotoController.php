<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Content\Actions\ReplacePagePhoto;
use App\Modules\Content\Http\Requests\Admin\SavePagePhotoRequest;
use App\Modules\Content\Http\Requests\Admin\StorePagePhotoRequest;
use App\Modules\Content\Support\PagePhotos;
use App\Modules\Settings\Actions\SaveSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The panel's „Zdjęcia stron”: every photo of the shop's own pages by place, with the photo Kasia puts in instead,
 * the point that stays in view and her description. Each place saves on its own and keeps its own mistakes.
 */
class PagePhotoController extends Controller
{
    public function edit(PagePhotos $photos): View
    {
        return view('content::admin.page-photos', [
            'pages' => collect(PagePhotos::keys())
                ->map(fn (string $key) => ['place' => PagePhotos::place($key), 'photo' => $photos->get($key)])
                ->groupBy(fn (array $row) => $row['place']['page']),
        ]);
    }

    public function store(StorePagePhotoRequest $request, string $place, ReplacePagePhoto $replace): RedirectResponse
    {
        $replace($place, $request->file('photo'));

        return $this->saved($place, 'Zdjęcie wgrane. Klienci już je widzą — ustaw kadr, jeśli trzeba.');
    }

    public function update(SavePagePhotoRequest $request, string $place, PagePhotos $photos, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($photos->with($place, $request->changes()));

        return $this->saved($place, 'Kadr zapisany. Klienci już go widzą.');
    }

    public function destroy(string $place, ReplacePagePhoto $replace): RedirectResponse
    {
        $replace($place, false);

        return $this->saved($place, 'Zdjęcie zdjęte ze strony. Jego miejsce zostaje puste.');
    }

    public function restore(string $place, ReplacePagePhoto $replace): RedirectResponse
    {
        $replace($place, null);

        return $this->saved($place, 'Wróciło pierwsze zdjęcie z pierwszym kadrem.');
    }

    private function saved(string $place, string $status): RedirectResponse
    {
        return to_route('admin.page-photos.edit')->withFragment($place)->with('panel_status', $status);
    }
}
