<?php

namespace App\Modules\MugConfigurator\Actions;

use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\MugConfigurator\Support\MugPhotoFile;
use App\Modules\Settings\Actions\SaveSettings;
use Illuminate\Http\UploadedFile;

/**
 * Puts a new photo under the customer's text, or brings back the photo from zdjecia/ (MugPhotoFile).
 * The photo it replaces is deleted, unless it is one from zdjecia/.
 */
class ReplaceMugPhoto
{
    public function __construct(private MugOptions $options, private SaveSettings $saveSettings) {}

    public function __invoke(?UploadedFile $photo): void
    {
        $previous = $this->options->photoPath();
        $path = $photo !== null ? MugPhotoFile::store($photo, 'kubek-z-napisem') : MugOptions::DEFAULT_PHOTO;

        ($this->saveSettings)(['mug_configurator_image' => $path]);

        if ($previous !== $path) {
            MugPhotoFile::delete($previous);
        }
    }
}
