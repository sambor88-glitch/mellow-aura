<?php

namespace App\Modules\Catalog\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lets the browser pick the smaller copy of a product photo on a phone. The thumbnail counts too: a card half a phone
 * wide needs about 400 px, and the 720 px copy was most of what the shop sent before its first paint (MA-232).
 */
class ProductPhoto
{
    /**
     * Widths declared in the srcset, taken from the conversions in Product::registerMediaConversions()
     * (OrderItem has the same ones for the photo of a sold piece).
     */
    private const WIDTHS = ['thumb' => 400, 'phone' => 720, 'card' => 1200];

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

    /**
     * How wide a product card shows its photo: about 400 px on a computer, half or the whole phone screen.
     */
    public static function cardSizes(int $phoneColumns = 2): string
    {
        return '(min-width: 640px) 400px, '.($phoneColumns > 1 ? 'calc(50vw - 35px)' : 'calc(100vw - 56px)');
    }

    /**
     * The layout's preloadImage for a page that opens with this photo, with the same src, srcset and sizes as its <img>.
     *
     * @return array{src: string, srcset: ?string, sizes: string}
     */
    public static function preload(Media $photo, string $sizes): array
    {
        return ['src' => $photo->getAvailableUrl(['card']), 'srcset' => self::srcset($photo), 'sizes' => $sizes];
    }
}
