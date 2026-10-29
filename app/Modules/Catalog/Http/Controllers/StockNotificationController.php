<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\SubscribeToRestock;
use App\Modules\Catalog\Http\Requests\SubscribeToRestockRequest;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StockNotification;
use App\Modules\Localization\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * „Napisz do mnie, gdy wróci” on a sold-out size (MA-266). The page remembers in the session which sizes this
 * browser waits for, so it can say so and offer „Wypisz mnie” — the token in the session is the only key to the row.
 */
class StockNotificationController extends Controller
{
    /** Session key: variant id => token. */
    public const SESSION = 'stock_notifications';

    public function store(SubscribeToRestockRequest $request, SubscribeToRestock $subscribe): RedirectResponse
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($request->integer('variant_id'));

        // Only a size that is sold out and has a page in this language.
        abort_if($variant->isInStock() || ! $variant->product->is_published || ! $variant->product->availableIn(Locales::current()) || $variant->price() === null, 404);

        // A bot sees the same answer as a person, and nothing is kept.
        if (! $request->isFromBot()) {
            $notification = $subscribe($variant, $request->string('email')->toString(), Locales::current());
            $request->session()->put(self::SESSION.'.'.$variant->id, $notification->token);
        }

        return $this->backToProduct($variant)->with('restock_status', 'subscribed');
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $notification = StockNotification::query()->where('token', $token)->with('variant.product')->first();
        $variants = $request->session()->get(self::SESSION, []);
        $request->session()->put(self::SESSION, array_filter($variants, fn (string $kept) => ! hash_equals($kept, $token)));

        if ($notification === null) {
            return to_route('shop.index');
        }

        $notification->delete();

        return $this->backToProduct($notification->variant)->with('restock_status', 'unsubscribed');
    }

    private function backToProduct(ProductVariant $variant): RedirectResponse
    {
        return redirect()->to(route('product.show', $variant->product).'?wariant='.$variant->id.'#powiadom');
    }
}
