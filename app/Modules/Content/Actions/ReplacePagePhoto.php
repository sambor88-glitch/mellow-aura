<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Support\PagePhotos;
use App\Modules\Content\Support\PagePhotoSizes;
use App\Modules\Settings\Actions\SaveSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Puts Kasia's photo in a place of PagePhotos, takes the photo off the page (no photo) or brings back the one from
 * zdjecia/. A new photo is turned upright, scaled down to 2400 px and saved again as WebP, which drops EXIF and the GPS
 * position of the studio. The point and the product of the old photo mean nothing for the new one, so they go back to
 * the place's own.
 * The uploaded photo it replaces is deleted with its smaller copies; photos from zdjecia/ stay.
 */
class ReplacePagePhoto
{
    private const LONGEST_SIDE = 2400;

    public function __construct(private PagePhotos $photos, private PagePhotoSizes $sizes, private SaveSettings $saveSettings) {}

    /**
     * @param  UploadedFile|false|null  $photo  a new photo, false for no photo, null for the photo from zdjecia/
     */
    public function __invoke(string $place, UploadedFile|false|null $photo): void
    {
        $previous = $this->photos->get($place)->path;

        if ($photo === null) {
            ($this->saveSettings)($this->photos->without($place, ['path', 'focus', 'focus_phone', 'alt', 'product_id']));
        } else {
            $path = $photo === false ? null : $this->store($photo, $place);
            ($this->saveSettings)($this->photos->with($place, ['path' => $path, 'focus' => null, 'focus_phone' => null, 'alt' => null, 'product_id' => null]));
        }

        if ($previous !== null && str_starts_with($previous, PagePhotos::DIRECTORY.'/')) {
            Storage::disk('public')->delete($previous);
            $this->sizes->forget($previous);
        }
    }

    private function store(UploadedFile $photo, string $place): string
    {
        $webp = tempnam(sys_get_temp_dir(), 'page');

        Image::useImageDriver('gd')
            ->loadFile($photo->getRealPath())
            ->fit(Fit::Max, self::LONGEST_SIDE, self::LONGEST_SIDE)
            ->quality(85)
            ->format('webp')
            ->save($webp);

        $path = PagePhotos::DIRECTORY.'/'.$place.'-'.Str::lower(Str::random(6)).'.webp';
        Storage::disk('public')->put($path, (string) file_get_contents($webp));
        @unlink($webp);

        return $path;
    }
}
