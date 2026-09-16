<?php

namespace App\Modules\Gifts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Gifts\Support\VoucherValidity;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

/**
 * /voucher-na-warsztaty-ceramiczne: every voucher on sale on one page, with the first line of its workshop
 * description from the panel and how it is used. Buying still happens on each voucher's product page,
 * where the name and the dedication go in.
 */
class VoucherPageController extends Controller
{
    public function __invoke(Settings $settings): View
    {
        $vouchers = Product::query()->live()
            ->whereRelation('category', 'group', CategoryGroup::Workshops->value)
            ->with(['category', 'variants', 'media'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $notes = (array) $settings->get('voucher_workshop_notes', []);

        return view('gifts::vouchers.index', [
            'lead' => $settings->get('text_vouchers_lead'),
            'vouchers' => $vouchers,
            'expectations' => $vouchers
                ->mapWithKeys(fn (Product $voucher) => [$voucher->id => is_array($notes[$voucher->slug] ?? null) ? ($notes[$voucher->slug]['expect'] ?? null) : null])
                ->filter(fn (mixed $note) => is_string($note) && trim($note) !== ''),
            'validity' => VoucherValidity::label((int) $settings->get('voucher_validity_months', 12)),
            'howToUse' => $settings->get('text_voucher_how_to_use'),
            'phone' => $settings->get('contact_phone'),
        ]);
    }
}
