<?php

namespace App\Modules\Content\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

/**
 * Smaller copies of a page photo for srcset, so a phone does not download a 2400 px photo for a frame 100 px wide (MA-232).
 *
 * A copy is made the first time a page asks for it and stays on the public disk next to the uploads, under a name
 * tied to the photo, so a new photo in the same place gets new copies. Only copies narrower than the photo are made;
 * the photo itself stays the largest candidate. Anything that goes wrong leaves the page with the plain src.
 */
class PagePhotoSizes
{
    public const DIRECTORY = PagePhotos::DIRECTORY.'/sizes';

    /** @var list<int> */
    private const WIDTHS = [400, 800, 1600];

    /**
     * The srcset for a photo path saved in PagePhotos, or null when there is nothing smaller to offer.
     */
    public function srcset(string $path, string $url): ?string
    {
        try {
            $source = $this->source($path);
            $width = $source !== null ? (getimagesize($source)[0] ?? 0) : 0;
            $candidates = [];

            foreach (self::WIDTHS as $size) {
                if ($size >= $width) {
                    break;
                }

                $copy = $this->copyPath($path, $source, $size);

                if (! Storage::disk('public')->exists($copy) && ! $this->make($source, $copy, $size)) {
                    return null;
                }

                $candidates[] = Storage::disk('public')->url($copy).' '.$size.'w';
            }

            return $candidates === [] ? null : implode(', ', [...$candidates, $url.' '.$width.'w']);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Deletes the copies of an uploaded photo that left its place. */
    public function forget(string $path): void
    {
        $disk = Storage::disk('public');
        $prefix = self::DIRECTORY.'/'.pathinfo($path, PATHINFO_FILENAME).'-';

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if (str_starts_with($file, $prefix)) {
                $disk->delete($file);
            }
        }
    }

    private function source(string $path): ?string
    {
        $file = str_starts_with($path, 'zdjecia/') ? base_path($path) : Storage::disk('public')->path($path);

        return is_file($file) ? $file : null;
    }

    /** The size of the file is in the name: a photo from zdjecia/ changed in the repository gets new copies. */
    private function copyPath(string $path, string $source, int $width): string
    {
        $version = substr(md5($path.'|'.filesize($source)), 0, 8);

        return self::DIRECTORY.'/'.pathinfo($path, PATHINFO_FILENAME).'-'.$version.'-'.$width.'.webp';
    }

    /** Two visitors at once make the copy once; the one who waits gets the plain src this time. */
    private function make(string $source, string $copy, int $width): bool
    {
        return (bool) Cache::lock('page-photo-size:'.$copy, 30)->get(function () use ($source, $copy, $width) {
            $disk = Storage::disk('public');

            if ($disk->exists($copy)) {
                return true;
            }

            $temporary = tempnam(sys_get_temp_dir(), 'size');

            try {
                Image::useImageDriver('gd')
                    ->loadFile($source)
                    ->fit(Fit::Max, $width, $width * 4)
                    ->quality(80)
                    ->format('webp')
                    ->save($temporary);

                return $disk->put($copy, (string) file_get_contents($temporary));
            } finally {
                @unlink($temporary);
            }
        });
    }
}
