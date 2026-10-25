<?php

namespace App\Modules\Settings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Http\Requests\Admin\SaveSeoPagesRequest;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\PageSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The panel's „Google”: the title and description Google shows for each fixed page, in Polish and, where the page
 * has an English twin, in English.
 */
class SeoPagesController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('settings::admin.seo-pages', [
            'saved' => (array) $settings->get(PageSeo::SETTING, []),
        ]);
    }

    public function update(SaveSeoPagesRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings([PageSeo::SETTING => $request->pages()]);

        return to_route('admin.seo-pages.edit')->with('panel_status', 'Zapisane. Google zobaczy zmianę przy następnej wizycie.');
    }
}
