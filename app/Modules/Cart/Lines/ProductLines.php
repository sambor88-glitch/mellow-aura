<?php

namespace App\Modules\Cart\Lines;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineType;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Product sizes from the catalogue. A hidden product drops out of the cart.
 */
class ProductLines implements LineType
{
    public function __construct(protected Settings $settings) {}

    public function lines(array $rows): iterable
    {
        $variants = $this->publishedVariants(array_column($rows, 'variant_id'));

        foreach ($rows as $key => $row) {
            if ($variant = $variants->get($row['variant_id'] ?? null)) {
                yield $key => $this->line($key, (int) $row['quantity'], $variant, $row);
            }
        }
    }

    public function fromRequest(Request $request): CartLine
    {
        $variant = $this->requestedVariant($request);

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

    /**
     * The line for a session row of this type.
     *
     * @param  array<string, mixed>  $row
     */
    protected function line(string $key, int $quantity, ProductVariant $variant, array $row): ProductLine
    {
        return new ProductLine($key, $quantity, $variant, $row['custom_text'] ?? null);
    }

    /**
     * @param  list<mixed>  $ids
     * @return Collection<int, ProductVariant>
     */
    protected function publishedVariants(array $ids): Collection
    {
        return ProductVariant::query()
            ->with('product.media', 'product.category')
            ->whereKey($ids)
            ->whereRelation('product', 'is_published', true)
            ->get()
            ->keyBy('id');
    }

    protected function requestedVariant(Request $request): ProductVariant
    {
        return ProductVariant::query()
            ->with('product.media', 'product.category')
            ->whereRelation('product', 'is_published', true)
            ->findOrFail($request->integer('variant_id'));
    }
}
