<?php

namespace App\Modules\Settings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Http\Requests\Admin\SaveMaterialTilesRequest;
use App\Modules\Settings\Http\Requests\Admin\SaveShippingRequest;
use App\Modules\Settings\Http\Requests\Admin\SaveStudioDetailsRequest;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The panel's „Ustawienia”, like the prototype: delivery and fees, the studio's details and the material
 * tiles. Each card saves on its own, so a mistake in one does not hold back the others. The workshop
 * deposit joins the page together with workshop booking.
 */
class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('settings::admin.edit', [
            'freeFrom' => $settings->get('free_shipping_threshold'),
            'giftWrapPrice' => $settings->get('gift_wrap_price'),
            'shippingMethods' => collect((array) $settings->get('shipping_methods', []))
                ->filter(fn (mixed $method) => is_array($method) && filled($method['code'] ?? null))
                ->values(),
            'studio' => collect(SaveStudioDetailsRequest::KEYS)->mapWithKeys(fn (string $key) => [$key => $settings->get($key)])->all(),
            'tiles' => array_values((array) $settings->get('material_tiles', [])),
        ]);
    }

    public function shipping(SaveShippingRequest $request, Settings $settings, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings($settings));

        return to_route('admin.settings.edit')
            ->withFragment('dostawa')
            ->with('panel_status', 'Zapisane. Koszyk już liczy według nowych cen.');
    }

    public function studio(SaveStudioDetailsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.settings.edit')
            ->withFragment('pracownia')
            ->with('panel_status', 'Dane pracowni zapisane');
    }

    public function materials(SaveMaterialTilesRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.settings.edit')
            ->withFragment('materialy')
            ->with('panel_status', 'Kafelki zapisane');
    }
}
