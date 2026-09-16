<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\Admin\PhotoRules;
use App\Modules\Content\Enums\Service;
use App\Modules\Content\Http\Requests\Admin\SaveServicePricesRequest;
use App\Modules\Content\Http\Requests\Admin\SaveServiceStepsRequest;
use App\Modules\Content\Http\Requests\Admin\SaveServiceTextsRequest;
use App\Modules\Content\Models\ServiceExample;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * „Usługi” in the panel: for „Z Twojej apaszki” and „Odcisk Twojej rośliny” the „przed i po” photos, the sentences,
 * the price list and the steps. Each card saves on its own.
 */
class ServicesController extends Controller
{
    public function edit(Settings $settings, string $service = 'apaszka'): View
    {
        $current = Service::fromSlug($service) ?? abort(404);
        $rows = fn (string $key, array $fields) => collect((array) $settings->get($current->setting($key), []))
            ->filter(fn (mixed $row) => is_array($row))
            ->map(fn (array $row) => collect($fields)->mapWithKeys(fn (string $field) => [$field => $row[$field] ?? ''])->all())
            ->values()
            ->all();

        return view('content::admin.services', [
            'service' => $current,
            'texts' => collect(array_keys(SaveServiceTextsRequest::FIELDS))->mapWithKeys(fn (string $field) => [$field => $settings->get($current->setting($field))])->all(),
            'prices' => collect($rows('prices', ['label', 'price_gross', 'note']))
                ->map(fn (array $row) => ['label' => $row['label'], 'price' => is_numeric($row['price_gross']) ? Money::input((int) $row['price_gross']) : '', 'note' => $row['note']])
                ->all(),
            'steps' => $rows('steps', ['title', 'text']),
            'examples' => ServiceExample::query()->where('service', $current)->ordered()->with('media')->get(),
            'photoLimits' => PhotoRules::limits(),
        ]);
    }

    public function texts(SaveServiceTextsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return $this->back($request->service(), 'teksty', 'Teksty zapisane. Klienci już je widzą.');
    }

    public function prices(SaveServicePricesRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return $this->back($request->service(), 'cennik', $request->moved() ? 'Kolejność zmieniona. Klienci już ją widzą.' : 'Cennik zapisany');
    }

    public function steps(SaveServiceStepsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return $this->back($request->service(), 'kroki', $request->moved() ? 'Kolejność zmieniona. Klienci już ją widzą.' : 'Kroki zapisane');
    }

    private function back(Service $service, string $card, string $status): RedirectResponse
    {
        return to_route('admin.services.edit', $service->slug())->withFragment($card)->with('panel_status', $status);
    }
}
