<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Models\OrderItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Puts Kasia's photo of one sold piece on its page (MA-290), in place of the one before. Like a product photo it is
 * turned upright, scaled down to 2400 px and saved again as WebP, which drops EXIF and the GPS position of the studio.
 * The file is named after the piece and a random part only: nothing in it points to the order or the buyer.
 */
class SetPiecePhoto
{
    private const LONGEST_SIDE = 2400;

    public function __invoke(OrderItem $item, UploadedFile $photo): void
    {
        $webp = tempnam(sys_get_temp_dir(), 'piece');

        Image::useImageDriver('gd')
            ->loadFile($photo->getRealPath())
            ->fit(Fit::Max, self::LONGEST_SIDE, self::LONGEST_SIDE)
            ->quality(85)
            ->format('webp')
            ->save($webp);

        $item->addMedia($webp)
            ->usingFileName((Str::slug($item->product_name) ?: 'sztuka').'-'.Str::lower(Str::random(8)).'.webp')
            ->toMediaCollection('photo');
    }
}
