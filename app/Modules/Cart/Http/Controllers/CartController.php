<?php

namespace App\Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\Lines\ProductLine;
use App\Modules\Cart\LineTypes;
use App\Modules\Localization\Support\Locales;
use App\Modules\Shared\Support\AnalyticsItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Every change answers with the drawer's fresh content, so the page updates without a reload.
 * Without JavaScript the forms still work and bring the customer back to the same page.
 */
class CartController extends Controller
{
    /**
     * A form names the kind of line it adds in "type"; product forms may leave it out.
     */
    public function store(Request $request, Cart $cart, LineTypes $types): JsonResponse|RedirectResponse
    {
        $type = $request->input('type', ProductLine::TYPE);
        $lineType = is_string($type) ? $types->get($type) : null;

        abort_if($lineType === null, 404);

        $line = $lineType->fromRequest($request);

        // Nothing goes into a euro basket without a euro price.
        abort_unless($line->pricedIn(Locales::currency()), 404);

        $added = $cart->add($line);
        // What went in, for Google Analytics; the page sends it only after a yes to statistics.
        $analytics = $added > 0
            ? ['name' => 'add_to_cart', 'params' => AnalyticsItem::params($line->unitPrice() * $added, [$line->withQuantity($added)->analyticsItem()])]
            : null;

        return $this->respond($request, $cart, $added === $line->quantity ? $line->addedNotice() : $line->limitNotice(), $analytics);
    }

    public function update(Request $request, Cart $cart, string $line): JsonResponse|RedirectResponse
    {
        $wanted = (int) $request->validate(
            ['quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY]],
            ['quantity.max' => CartLine::tooManyNotice()],
        )['quantity'];

        $current = $cart->lines()->get($line);
        $quantity = $cart->update($line, $wanted);

        return $this->respond($request, $cart, $current && $quantity < $wanted ? $current->limitNotice() : null);
    }

    public function destroy(Request $request, Cart $cart, string $line): JsonResponse|RedirectResponse
    {
        $cart->remove($line);

        return $this->respond($request, $cart, null);
    }

    /**
     * @param  array{name: string, params: array<string, mixed>}|null  $analytics
     */
    private function respond(Request $request, Cart $cart, ?string $notice, ?array $analytics = null): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back();
        }

        $data = [
            'count' => $cart->count(),
            'content' => view('cart::content')->render(),
            'notice' => $notice,
        ];

        if ($analytics !== null) {
            $data['analytics'] = $analytics;
        }

        return response()->json($data);
    }
}
