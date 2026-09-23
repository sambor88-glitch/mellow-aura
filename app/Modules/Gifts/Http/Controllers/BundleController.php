<?php

namespace App\Modules\Gifts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Cart;
use App\Modules\Gifts\Cart\GiftWrapLine;
use App\Modules\Gifts\Cart\GiftWrapLines;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

/**
 * /zestawy-prezentowe: the sets on sale now and gift wrapping for the whole order.
 */
class BundleController extends Controller
{
    public function __invoke(Cart $cart, GiftWrapLines $giftWrap, Settings $settings): View
    {
        return view('gifts::bundles.index', [
            'bundles' => Bundle::query()->live()->with('items.variant.product.media')->orderBy('sort_order')->orderBy('id')->get(),
            'wrapOffered' => $giftWrap->offered(),
            'wrapPrice' => (int) $settings->get('gift_wrap_price', 0),
            'wrapped' => $cart->lines()->has(GiftWrapLine::KEY),
        ]);
    }
}
