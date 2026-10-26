<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Moves a photo one place earlier or later. The first photo is the cover in the shop.
 */
class MoveProductPhoto
{
    public function __invoke(Product $product, Media $photo, int $direction): void
    {
        $ids = $product->getMedia('images')->pluck('id')->all();
        $from = array_search($photo->id, $ids, true);

        if ($from === false || ! isset($ids[$from + $direction])) {
            return;
        }

        [$ids[$from], $ids[$from + $direction]] = [$ids[$from + $direction], $ids[$from]];

        Media::setNewOrder($ids);
    }
}
