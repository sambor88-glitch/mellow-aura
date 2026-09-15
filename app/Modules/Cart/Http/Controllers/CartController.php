<?php

namespace App\Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Cart;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Every change answers with the drawer's fresh content, so the page updates without a reload.
 * Without JavaScript the forms still work and bring the customer back to the same page.
 */
class CartController extends Controller
{
    private const TOO_MANY = 'Większą liczbę sztuk zrobię na zamówienie — napisz do mnie';

    public function store(Request $request, Cart $cart, Settings $settings): JsonResponse|RedirectResponse
    {
        $variant = ProductVariant::query()
            ->with('product')
            ->whereRelation('product', 'is_published', true)
            ->findOrFail($request->integer('variant_id'));

        $data = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
            'custom_text' => [Rule::requiredIf($variant->takesCustomText()), 'nullable', 'string', 'max:'.(int) $settings->get('stamp_text_max_chars', 22)],
        ], [
            'quantity.max' => self::TOO_MANY,
            'custom_text.required' => 'Napisz, co mam wbić w glinę',
            'custom_text.max' => 'Zmieszczę najwyżej :max znaków',
        ]);

        $quantity = (int) ($data['quantity'] ?? 1);

        // Letters are stamped in capitals; a text sent for a product without stamping is ignored.
        $customText = $variant->takesCustomText()
            ? mb_strtoupper((string) preg_replace('/\s+/u', ' ', trim($data['custom_text'])))
            : null;

        $added = $cart->add($variant, $quantity, $customText);

        return $this->respond($request, $cart, $added === $quantity ? $variant->product->name.' — dodane do koszyka' : $this->shelfNotice($variant));
    }

    public function update(Request $request, Cart $cart, string $line): JsonResponse|RedirectResponse
    {
        $wanted = (int) $request->validate(
            ['quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY]],
            ['quantity.max' => self::TOO_MANY],
        )['quantity'];

        $current = $cart->lines()->get($line);
        $quantity = $cart->update($line, $wanted);

        return $this->respond($request, $cart, $current && $quantity < $wanted ? $this->shelfNotice($current->variant) : null);
    }

    public function destroy(Request $request, Cart $cart, string $line): JsonResponse|RedirectResponse
    {
        $cart->remove($line);

        return $this->respond($request, $cart, null);
    }

    private function respond(Request $request, Cart $cart, ?string $notice): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back();
        }

        return response()->json([
            'count' => $cart->count(),
            'content' => view('cart::content')->render(),
            'notice' => $notice,
        ]);
    }

    private function shelfNotice(ProductVariant $variant): string
    {
        return match ($variant->stock) {
            null => self::TOO_MANY,
            0 => 'Tej sztuki już nie ma na półce — kolejną zrobię na zamówienie',
            1 => 'To ostatnia sztuka — kolejną zrobię na zamówienie',
            default => 'Na półce mam '.$variant->stock.' szt. — więcej zrobię na zamówienie',
        };
    }
}
