<?php

namespace App\Modules\Gifts\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Gifts\Actions\SaveBundle;
use App\Modules\Gifts\Http\Requests\Admin\SaveBundleRequest;
use App\Modules\Gifts\Http\Requests\Admin\SaveBundleTextsRequest;
use App\Modules\Gifts\Http\Requests\Admin\SaveGiftFinderRequest;
use App\Modules\Gifts\Http\Requests\Admin\SaveVoucherNotesRequest;
use App\Modules\Gifts\Http\Requests\Admin\SaveVoucherSettingsRequest;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * „Prezenty i zestawy” in the panel: the gift sets with their parts and discounts, the sentences on
 * their page, the budgets in „Szukam prezentu”, the voucher settings and the workshop description on each
 * voucher. Each card saves on its own.
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
            'budgets' => collect((array) $settings->get('gift_budget_ranges', []))
                ->filter(fn (mixed $range) => is_array($range))
                ->map(fn (array $range) => [
                    'min' => is_numeric($range['min_gross'] ?? null) ? intdiv((int) $range['min_gross'], 100) : null,
                    'max' => is_numeric($range['max_gross'] ?? null) ? intdiv((int) $range['max_gross'], 100) : null,
                ])
                ->values()
                ->all(),
            'finderTexts' => ['text_gifts_lead' => $settings->get('text_gifts_lead'), 'text_gifts_voucher_note' => $settings->get('text_gifts_voucher_note')],
            'voucherSettings' => [
                'voucher_validity_months' => $settings->get('voucher_validity_months', 12),
                'voucher_recipient_name_max_chars' => $settings->get('voucher_recipient_name_max_chars', 40),
                'voucher_dedication_max_chars' => $settings->get('voucher_dedication_max_chars', 180),
                'text_voucher_how_to_use' => $settings->get('text_voucher_how_to_use'),
                'text_vouchers_lead' => $settings->get('text_vouchers_lead'),
            ],
            'voucherProducts' => Product::query()
                ->whereRelation('category', 'group', CategoryGroup::Workshops->value)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'slug']),
            'voucherNotes' => (array) $settings->get('voucher_workshop_notes', []),
            'hasWhatsApp' => filled($settings->get('contact_phone')),
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

    public function giftFinder(SaveGiftFinderRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.gifts.edit')->withFragment('szukam-prezentu')->with('panel_status', 'Zapisane. „Szukam prezentu” już tak wygląda.');
    }

    public function vouchers(SaveVoucherSettingsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.gifts.edit')->withFragment('vouchery')->with('panel_status', 'Zapisane. Nowe vouchery już tak wyglądają.');
    }

    public function voucherNotes(SaveVoucherNotesRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.gifts.edit')->withFragment('opis-warsztatu')->with('panel_status', 'Zapisane. Vouchery w PDF już mają ten opis.');
    }
}
