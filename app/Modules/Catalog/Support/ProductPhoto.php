<?php

namespace App\Modules\Catalog\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lets the browser pick the smaller copy of a product photo on a phone.
 */
class ProductPhoto
{
    /**
     * Widths declared in the srcset, taken from the conversions in Product::registerMediaConversions().
     */
    private const WIDTHS = ['phone' => 720, 'card' => 1200];

    /**
     * A srcset built only from copies that are already on disk.
     *
     * Fit::Max never enlarges, so a photo narrower than the limit ends up in both copies at its own
     * width and the declared width is too high. The browser then picks a file no larger than the one
     * served today, so the page is never worse — it just saves nothing on that photo.
     */
    public static function srcset(Media $photo): ?string
    {
        $candidates = [];

        foreach (self::WIDTHS as $conversion => $width) {
            if ($photo->hasGeneratedConversion($conversion)) {
                $candidates[] = $photo->getUrl($conversion).' '.$width.'w';
            }
        }

        return count($candidates) > 1 ? implode(', ', $candidates) : null;
    }
}
