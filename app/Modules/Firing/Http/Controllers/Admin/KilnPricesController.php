<?php

namespace App\Modules\Firing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Firing\Http\Requests\Admin\SaveKilnPricesRequest;
use App\Modules\Firing\Support\KilnPrices;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Money;
use App\Modules\Shared\Support\OfferPrices;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * „Wypały” in the panel: the price list on the firing page. The batch form and its list come after the holidays.
 */
class KilnPricesController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('firing::admin.edit', [
            'lead' => $settings->get('text_kiln_lead'),
            'note' => $settings->get('text_kiln_note'),
            'prices' => collect((array) $settings->get('kiln_prices', []))
                ->filter(fn (mixed $row) => is_array($row))
                ->map(fn (array $row) => [
                    'label' => $row['label'] ?? '',
                    'price' => is_numeric($row['price_gross'] ?? null) ? Money::input((int) $row['price_gross']) : '',
                    'compare_at' => is_numeric($row['compare_at_price'] ?? null) ? Money::input((int) $row['compare_at_price']) : '',
                    'unit_label' => $row['unit_label'] ?? '',
                    'note' => $row['note'] ?? '',
                    'code' => $row['code'] ?? '',
                    'unit' => $row['unit'] ?? '',
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(SaveKilnPricesRequest $request, Settings $settings, SaveSettings $saveSettings, OfferPrices $offerPrices): RedirectResponse
    {
        $before = KilnPrices::prices((array) $settings->get(KilnPrices::LIST, []));
        $saveSettings($values = $request->settings());
        $offerPrices->record(KilnPrices::LIST, $before, KilnPrices::prices($values[KilnPrices::LIST]));

        return to_route('admin.firing.edit')
            ->withFragment('cennik')
            ->with('panel_status', $request->moved() ? 'Kolejność zmieniona. Klienci już ją widzą.' : 'Zapisane. Cennik wypałów już tak wygląda.');
    }
}
