<?php

namespace App\Modules\Gifts\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Gifts\Actions\SaveBundle;
use App\Modules\Gifts\Http\Requests\Admin\SaveBundleRequest;
use App\Modules\Gifts\Http\Requests\Admin\SaveBundleTextsRequest;
use App\Modules\Gifts\Http\Requests\Admin\SaveVoucherSettingsRequest;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * „Prezenty i zestawy” in the panel: the gift sets with their parts and discounts, the sentences on
 * their page and the voucher settings. Each card saves on its own.
 */
class GiftsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('gifts::admin.edit', [
            'bundles' => Bundle::query()->with('items.variant.product')->orderBy('sort_order')->orderBy('id')->get(),
            // Every size from the shop except vouchers, grouped by product.
            'products' => Product::query()
                ->with(['variants' => fn ($query) => $query->orderBy('id')])
                ->whereRelation('category', 'group', '!=', CategoryGroup::Workshops->value)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'texts' => collect(SaveBundleTextsRequest::KEYS)->mapWithKeys(fn (string $key) => [$key => $settings->get($key)])->all(),
            'voucherSettings' => [
                'voucher_validity_months' => $settings->get('voucher_validity_months', 12),
                'voucher_recipient_name_max_chars' => $settings->get('voucher_recipient_name_max_chars', 40),
                'voucher_dedication_max_chars' => $settings->get('voucher_dedication_max_chars', 180),
                'text_voucher_how_to_use' => $settings->get('text_voucher_how_to_use'),
            ],
        ]);
    }

    public function storeBundle(SaveBundleRequest $request, SaveBundle $saveBundle): RedirectResponse
    {
        $bundle = $saveBundle(null, $request->bundle());

        return to_route('admin.gifts.edit')
            ->withFragment('zestaw-'.$bundle->id)
            ->with('panel_status', $bundle->name.' — '.($bundle->is_published ? 'widać na stronie zestawów' : 'zapisany jako ukryty'));
    }

    public function updateBundle(SaveBundleRequest $request, Bundle $bundle, SaveBundle $saveBundle): RedirectResponse
    {
        $bundle = $saveBundle($bundle, $request->bundle());

        return to_route('admin.gifts.edit')
            ->withFragment('zestaw-'.$bundle->id)
            ->with('panel_status', $bundle->is_published ? 'Zapisane. Klienci już to widzą.' : $bundle->name.' — ukryty na stronie');
    }

    public function texts(SaveBundleTextsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.gifts.edit')->withFragment('teksty')->with('panel_status', 'Teksty zapisane. Klienci już je widzą.');
    }

    public function vouchers(SaveVoucherSettingsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.gifts.edit')->withFragment('vouchery')->with('panel_status', 'Zapisane. Nowe vouchery już tak wyglądają.');
    }
}
