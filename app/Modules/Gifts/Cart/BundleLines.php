<?php

namespace App\Modules\Gifts\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineType;
use App\Modules\Gifts\Models\Bundle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Gift sets. A set that was hidden, or lost a part from the shop, drops out of the cart.
 */
class BundleLines implements LineType
{
    public function lines(array $rows): iterable
    {
        $bundles = $this->liveBundles()->whereKey(array_column($rows, 'bundle_id'))->get()->keyBy('id');

        foreach ($rows as $key => $row) {
            if ($bundle = $bundles->get($row['bundle_id'] ?? null)) {
                yield $key => new BundleLine($key, (int) $row['quantity'], $bundle);
            }
        }
    }

    public function fromRequest(Request $request): CartLine
    {
        $bundle = $this->liveBundles()->findOrFail($request->integer('bundle_id'));

        $data = $request->validate(
            ['quantity' => ['sometimes', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY]],
            ['quantity.max' => CartLine::tooManyNotice()],
        );

        return new BundleLine(BundleLine::keyFor($bundle), (int) ($data['quantity'] ?? 1), $bundle);
    }

    /**
     * @return Builder<Bundle>
     */
    private function liveBundles(): Builder
    {
        return Bundle::query()->live()->with('items.variant.product.media');
    }
}
