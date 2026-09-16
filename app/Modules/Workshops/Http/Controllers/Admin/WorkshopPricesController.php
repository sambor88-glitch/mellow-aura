<?php

namespace App\Modules\Workshops\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Money;
use App\Modules\Shared\Support\OfferPrices;
use App\Modules\Workshops\Http\Requests\Admin\SaveWorkshopTypesRequest;
use App\Modules\Workshops\Support\WorkshopTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * „Warsztaty” in the panel: the price list on the workshops page. Dates and places come after the holidays.
 */
class WorkshopPricesController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('workshops::admin.edit', [
            'lead' => $settings->get('text_workshops_lead'),
            'workshops' => collect((array) $settings->get('workshop_types', []))
                ->filter(fn (mixed $type) => is_array($type))
                ->map(fn (array $type) => [
                    'name' => $type['name'] ?? '',
                    'price' => is_numeric($type['price_gross'] ?? null) ? Money::input((int) $type['price_gross']) : '',
                    'compare_at' => is_numeric($type['compare_at_price'] ?? null) ? Money::input((int) $type['compare_at_price']) : '',
                    'unit_label' => $type['unit_label'] ?? '',
                    'duration_label' => $type['duration_label'] ?? '',
                    'group_label' => $type['group_label'] ?? '',
                    'summary' => $type['summary'] ?? '',
                    'includes' => implode("\n", (array) ($type['includes'] ?? [])),
                    'code' => $type['code'] ?? '',
                    'unit' => $type['unit'] ?? '',
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(SaveWorkshopTypesRequest $request, Settings $settings, SaveSettings $saveSettings, OfferPrices $offerPrices): RedirectResponse
    {
        $before = WorkshopTypes::prices((array) $settings->get(WorkshopTypes::LIST, []));
        $saveSettings($values = $request->settings());
        $offerPrices->record(WorkshopTypes::LIST, $before, WorkshopTypes::prices($values[WorkshopTypes::LIST]));

        return to_route('admin.workshops.edit')
            ->withFragment('cennik')
            ->with('panel_status', $request->moved() ? 'Kolejność zmieniona. Klienci już ją widzą.' : 'Zapisane. Cennik warsztatów już tak wygląda.');
    }
}
