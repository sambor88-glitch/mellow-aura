<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;

/**
 * A photo path saved in the panel. A photo from zdjecia/ comes with the site's build; a photo uploaded
 * in the panel lives on the public disk. A missing file gives null, so a page leaves the photo out
 * instead of showing a broken one.
 */
class SitePhoto
{
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'zdjecia/')) {
            return is_file(base_path($path)) ? Vite::asset($path) : null;
        }

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }
}
