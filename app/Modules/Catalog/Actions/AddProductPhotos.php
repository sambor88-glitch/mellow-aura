<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Adds photos sent from the panel. Loading a photo turns it upright by its EXIF orientation; it is then
 * scaled down to 2400 px and saved again as WebP. Saving again drops the EXIF data: a phone photo taken
 * in the studio carries its GPS position, and the studio's address never goes public.
 * File names follow the product, like the photos in zdjecia/.
 */
class AddProductPhotos
{
    private const LONGEST_SIDE = 2400;

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function __invoke(Product $product, array $photos): void
    {
        foreach ($photos as $photo) {
            $webp = tempnam(sys_get_temp_dir(), 'photo');

            Image::useImageDriver('gd')
                ->loadFile($photo->getRealPath())
                ->fit(Fit::Max, self::LONGEST_SIDE, self::LONGEST_SIDE)
                ->quality(85)
                ->format('webp')
                ->save($webp);

            $product->addMedia($webp)
                ->usingFileName(Str::slug($product->name).'-'.Str::lower(Str::random(6)).'.webp')
                ->toMediaCollection('images');
        }
    }
}
