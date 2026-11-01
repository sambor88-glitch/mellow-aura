<?php

namespace App\Modules\Settings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Http\Requests\Admin\SaveAnalyticsRequest;
use App\Modules\Settings\Http\Requests\Admin\SaveCompanyDetailsRequest;
use App\Modules\Settings\Http\Requests\Admin\SaveProductCardRequest;
use App\Modules\Settings\Http\Requests\Admin\SaveReviewRequestRequest;
use App\Modules\Settings\Http\Requests\Admin\SaveShippingRequest;
use App\Modules\Settings\Http\Requests\Admin\SaveStudioDetailsRequest;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The panel's „Ustawienia”, like the prototype: delivery and fees, the studio's details, the company's details
 * for the terms of sale, the sentences shared by product pages, and the mail asking for a Google review. Each card saves on its own, so a mistake in one does not hold back the others. The workshop
 * deposit joins the page together with workshop booking.
 */
class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('settings::admin.edit', [
            'freeFrom' => $settings->get('free_shipping_threshold'),
            'giftWrapPrice' => $settings->get('gift_wrap_price'),
            'dispatchDays' => ['dispatch_days_min' => $settings->get('dispatch_days_min'), 'dispatch_days_max' => $settings->get('dispatch_days_max')],
            'shippingMethods' => collect((array) $settings->get('shipping_methods', []))
                ->filter(fn (mixed $method) => is_array($method) && filled($method['code'] ?? null))
                ->values(),
            'studio' => collect(SaveStudioDetailsRequest::KEYS)->mapWithKeys(fn (string $key) => [$key => $settings->get($key)])->all(),
            'company' => collect(SaveCompanyDetailsRequest::KEYS)->mapWithKeys(fn (string $key) => [$key => $settings->get($key)])->all(),
            'productCard' => collect(SaveProductCardRequest::KEYS)->mapWithKeys(fn (string $key) => [$key => $settings->get($key)])->all(),
            'analyticsId' => $settings->get('google_analytics_id'),
            'reviewRequest' => ['review_request_url' => $settings->get('review_request_url'), 'review_request_days' => $settings->get('review_request_days')],
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

    public function company(SaveCompanyDetailsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.settings.edit')
            ->withFragment('firma')
            ->with('panel_status', 'Dane firmy zapisane. Regulamin i stopka już je pokazują.');
    }

    public function productCard(SaveProductCardRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.settings.edit')
            ->withFragment('karta-produktu')
            ->with('panel_status', 'Zapisane. Karty produktów i regulamin już to pokazują.');
    }

    public function analytics(SaveAnalyticsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.settings.edit')
            ->withFragment('statystyki')
            ->with('panel_status', $request->settings()['google_analytics_id'] ? 'Zapisane. Statystyki ruszą po zgodzie w okienku.' : 'Statystyki i okienko zgód wyłączone');
    }

    public function reviews(SaveReviewRequestRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $values = $request->settings();
        $saveSettings($values);

        return to_route('admin.settings.edit')
            ->withFragment('opinie')
            ->with('panel_status', $values['review_request_url'] && $values['review_request_days']
                ? 'Zapisane. Prośba o opinię wyjdzie '.$values['review_request_days'].($values['review_request_days'] === 1 ? ' dzień' : ' dni').' po zakończeniu zamówienia.'
                : 'Zapisane. Bez linku i liczby dni prośba o opinię nie wychodzi.');
    }
}
