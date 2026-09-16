<?php

namespace App\Modules\MugConfigurator\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MugConfigurator\Actions\ReplaceMugPhoto;
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
 * how much text fits, where it sits and its colour, and the sentences on the page. Each card saves on its own.
 */
class MugSettingsController extends Controller
{
    public function edit(MugOptions $options, Settings $settings): View
    {
        return view('mug-configurator::admin.edit', [
            'options' => $options,
            'isDefaultPhoto' => $options->photoPath() === MugOptions::DEFAULT_PHOTO,
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
