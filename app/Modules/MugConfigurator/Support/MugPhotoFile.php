<?php

namespace App\Modules\MugConfigurator\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * A photo Kasia uploads for the mug page: turned upright, scaled down to 2400 px and saved again as WebP on the
 * public disk, which drops EXIF and the GPS position of the studio.
 */
class MugPhotoFile
{
    public const DIRECTORY = 'mug-configurator';

    private const LONGEST_SIDE = 2400;

    /** Saves the photo and returns its path on the public disk. */
    public static function store(UploadedFile $photo, string $name): string
    {
        $webp = tempnam(sys_get_temp_dir(), 'mug');

        Image::useImageDriver('gd')
            ->loadFile($photo->getRealPath())
            ->fit(Fit::Max, self::LONGEST_SIDE, self::LONGEST_SIDE)
            ->quality(85)
            ->format('webp')
            ->save($webp);

        $path = self::DIRECTORY.'/'.$name.'-'.Str::lower(Str::random(6)).'.webp';
        Storage::disk('public')->put($path, (string) file_get_contents($webp));
        @unlink($webp);

        return $path;
    }

    /** Deletes a photo saved by store(); photos from zdjecia/ stay. */
    public static function delete(?string $path): void
    {
        if ($path !== null && str_starts_with($path, self::DIRECTORY.'/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
