<?php

namespace App\Modules\MugConfigurator\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MugConfigurator\Actions\ReplaceLetteringPhoto;
use App\Modules\MugConfigurator\Actions\ReplaceMugPhoto;
use App\Modules\MugConfigurator\Http\Requests\Admin\SaveLetteringPhotoRequest;
use App\Modules\MugConfigurator\Http\Requests\Admin\SaveMugLookRequest;
use App\Modules\MugConfigurator\Http\Requests\Admin\SaveMugPhotoRequest;
use App\Modules\MugConfigurator\Http\Requests\Admin\SaveMugSizesRequest;
use App\Modules\MugConfigurator\Http\Requests\Admin\SaveMugTextsRequest;
use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * „Kubek z napisem” in the panel, like the prototype's tab: the photo under the text, sizes with prices,
 * how much text fits, where it sits and its colour, the sentences on the page and two close-ups of a real,
 * fired text („Napis z bliska”). Each card saves on its own.
 */
class MugSettingsController extends Controller
{
    public function edit(MugOptions $options, Settings $settings): View
    {
        return view('mug-configurator::admin.edit', [
            'options' => $options,
            'isDefaultPhoto' => $options->photoPath() === MugOptions::DEFAULT_PHOTO,
            'lettering' => collect(MugOptions::LETTERING_PHOTOS)->map(fn (string $key, string $slot) => $options->letteringPhotoUrl($slot))->all(),
            'texts' => collect(SaveMugTextsRequest::FIELDS)->map(fn (int $max, string $key) => $settings->get($key))->all(),
        ]);
    }

    public function photo(SaveMugPhotoRequest $request, ReplaceMugPhoto $replacePhoto): RedirectResponse
    {
        $replacePhoto($request->file('photo'));

        return to_route('admin.mug.edit')->withFragment('zdjecie')->with('panel_status', 'Nowe zdjęcie kubka — klientki już na nim piszą');
    }

    public function resetPhoto(ReplaceMugPhoto $replacePhoto): RedirectResponse
    {
        $replacePhoto(null);

        return to_route('admin.mug.edit')->withFragment('zdjecie')->with('panel_status', 'Wróciło domyślne zdjęcie kubka');
    }

    public function letteringPhoto(SaveLetteringPhotoRequest $request, string $slot, ReplaceLetteringPhoto $replace): RedirectResponse
    {
        $replace($slot, $request->file('photo'));

        return to_route('admin.mug.edit')->withFragment('z-bliska')->with('panel_status', 'Zdjęcie napisu dodane — klienci widzą je na stronie kubka');
    }

    public function removeLetteringPhoto(string $slot, ReplaceLetteringPhoto $replace): RedirectResponse
    {
        $replace($slot, null);

        return to_route('admin.mug.edit')->withFragment('z-bliska')->with('panel_status', 'Zdjęcie napisu zdjęte ze strony');
    }

    public function sizes(SaveMugSizesRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.mug.edit')->withFragment('rozmiary')->with('panel_status', 'Zapisane. Koszyk już liczy według nowych cen.');
    }

    public function look(SaveMugLookRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.mug.edit')->withFragment('napis')->with('panel_status', 'Zapisane. Napis siedzi tak na stronie.');
    }

    public function texts(SaveMugTextsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.mug.edit')->withFragment('teksty')->with('panel_status', 'Teksty zapisane. Klienci już je widzą.');
    }
}
