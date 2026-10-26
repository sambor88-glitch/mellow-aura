<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Gifts\Models\Bundle;
use App\Modules\Gifts\Models\BundleItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Saves a gift set from the panel with its parts in the order they were chosen. A new set goes to the end.
 */
class SaveBundle
{
    /**
     * @param  array{name: string, description: ?string, discount_percent: int, is_published: bool, parts: list<int>}  $data
     */
    public function __invoke(?Bundle $bundle, array $data): Bundle
    {
        return DB::transaction(function () use ($bundle, $data) {
            $attributes = Arr::except($data, 'parts');

            if ($bundle === null) {
                $bundle = Bundle::create([...$attributes, 'sort_order' => (int) Bundle::query()->max('sort_order') + 1]);
            } else {
                $bundle->update($attributes);
            }

            BundleItem::query()->where('bundle_id', $bundle->id)->delete();

            foreach ($data['parts'] as $index => $variantId) {
                $bundle->items()->create(['product_variant_id' => $variantId, 'sort_order' => $index]);
            }

            return $bundle;
        });
    }
}
