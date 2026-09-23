<?php

namespace App\Modules\MugConfigurator\Actions;

use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\Settings\Actions\SaveSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Puts a new photo under the customer's text, or brings back the photo from zdjecia/. An uploaded photo
 * is turned upright, scaled down to 2400 px and saved again as WebP, which drops EXIF and the GPS
 * position of the studio. The photo it replaces is deleted, unless it is one from zdjecia/.
 */
class ReplaceMugPhoto
{
    private const LONGEST_SIDE = 2400;

    private const DIRECTORY = 'mug-configurator';

    public function __construct(private MugOptions $options, private SaveSettings $saveSettings) {}

    public function __invoke(?UploadedFile $photo): void
    {
        $previous = $this->options->photoPath();
        $path = MugOptions::DEFAULT_PHOTO;

        if ($photo !== null) {
            $webp = tempnam(sys_get_temp_dir(), 'mug');

            Image::useImageDriver('gd')
                ->loadFile($photo->getRealPath())
                ->fit(Fit::Max, self::LONGEST_SIDE, self::LONGEST_SIDE)
                ->quality(85)
                ->format('webp')
                ->save($webp);

            $path = self::DIRECTORY.'/kubek-z-napisem-'.Str::lower(Str::random(6)).'.webp';
            Storage::disk('public')->put($path, (string) file_get_contents($webp));
            @unlink($webp);
        }

        ($this->saveSettings)(['mug_configurator_image' => $path]);

        if ($previous !== $path && str_starts_with($previous, self::DIRECTORY.'/')) {
            Storage::disk('public')->delete($previous);
        }
    }
}
