<?php

namespace App\Modules\Cart\Lines;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineType;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
        ], [
            'quantity.max' => CartLine::tooManyNotice(),
        ]);

        return new ProductLine(ProductLine::keyFor($variant), (int) ($data['quantity'] ?? 1), $variant);
    }

    /**
     * The line for a session row of this type.
     *
     * @param  array<string, mixed>  $row
     */
    protected function line(string $key, int $quantity, ProductVariant $variant, array $row): ProductLine
    {
        return new ProductLine($key, $quantity, $variant);
    }

    /**
     * @param  list<mixed>  $ids
     * @return Collection<int, ProductVariant>
     */
    protected function publishedVariants(array $ids): Collection
    {
        return ProductVariant::query()
            ->with('product.media', 'product.category', 'prices')
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
            ->pricedIn()
            ->findOrFail($request->integer('variant_id'));
    }
}
