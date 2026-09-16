<?php

namespace App\Modules\Cart\Lines;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineType;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Product sizes from the catalogue. A hidden product drops out of the cart.
 */
class ProductLines implements LineType
{
    public function __construct(private Settings $settings) {}

    public function lines(array $rows): iterable
    {
        $variants = ProductVariant::query()
            ->with('product.media')
            ->whereKey(array_column($rows, 'variant_id'))
            ->whereRelation('product', 'is_published', true)
            ->get()
            ->keyBy('id');

        foreach ($rows as $key => $row) {
            if ($variant = $variants->get($row['variant_id'] ?? null)) {
                yield $key => new ProductLine($key, (int) $row['quantity'], $variant, $row['custom_text'] ?? null);
            }
        }
    }

    public function fromRequest(Request $request): CartLine
    {
        $variant = ProductVariant::query()
            ->with('product.media')
            ->whereRelation('product', 'is_published', true)
            ->findOrFail($request->integer('variant_id'));

        $data = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
            'custom_text' => [Rule::requiredIf($variant->takesCustomText()), 'nullable', 'string', 'max:'.(int) $this->settings->get('stamp_text_max_chars', 22)],
        ], [
            'quantity.max' => CartLine::TOO_MANY_NOTICE,
            'custom_text.required' => 'Napisz, co mam wbić w glinę',
            'custom_text.max' => 'Zmieszczę najwyżej :max znaków',
        ]);

        // Letters are stamped in capitals; a text sent for a product without stamping is ignored.
        $customText = $variant->takesCustomText()
            ? mb_strtoupper((string) preg_replace('/\s+/u', ' ', trim($data['custom_text'])))
            : null;

        return new ProductLine(ProductLine::keyFor($variant, $customText), (int) ($data['quantity'] ?? 1), $variant, $customText);
    }
}
