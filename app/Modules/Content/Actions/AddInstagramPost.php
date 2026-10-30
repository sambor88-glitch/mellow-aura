<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\InstagramPost;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * A post Kasia adds by hand (MA-274), at the end of her list: her photo with its description and the link
 * to the post on Instagram. The photo is turned upright, scaled down to 1600 px and saved again as WebP,
 * which drops EXIF and the GPS position of the studio.
 */
class AddInstagramPost
{
    private const LONGEST_SIDE = 1600;

    public function __invoke(UploadedFile $photo, string $permalink, string $alt): InstagramPost
    {
        $id = 'manual-'.Str::lower(Str::random(10));
        $path = 'instagram/'.$id.'.webp';
        $webp = tempnam(sys_get_temp_dir(), 'instagram');

        try {
            Image::useImageDriver('gd')
                ->loadFile($photo->getRealPath())
                ->fit(Fit::Max, self::LONGEST_SIDE, self::LONGEST_SIDE)
                ->quality(85)
                ->format('webp')
                ->save($webp);

            Storage::disk('public')->put($path, (string) file_get_contents($webp));
        } finally {
            @unlink($webp);
        }

        return InstagramPost::create([
            'external_id' => $id,
            'is_manual' => true,
            'sort_order' => (int) InstagramPost::query()->manual()->max('sort_order') + 1,
            'permalink' => $permalink,
            'alt_text' => $alt,
            'image_path' => $path,
            'posted_at' => now(),
        ]);
    }
}
