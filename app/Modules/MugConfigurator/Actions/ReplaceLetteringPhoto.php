<?php

namespace App\Modules\MugConfigurator\Actions;

use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\MugConfigurator\Support\MugPhotoFile;
use App\Modules\Settings\Actions\SaveSettings;
use Illuminate\Http\UploadedFile;

/**
 * One of the two close-ups of a real, fired text (MugOptions::LETTERING_PHOTOS): the letters against the light,
 * which shows how deep they go, and a finished text, which shows what the preview's font becomes in clay.
 * Without a photo the page shows nothing in its place.
 */
class ReplaceLetteringPhoto
{
    public function __construct(private MugOptions $options, private SaveSettings $saveSettings) {}

    public function __invoke(string $slot, ?UploadedFile $photo): void
    {
        $previous = $this->options->letteringPhotoPath($slot);
        $path = $photo !== null ? MugPhotoFile::store($photo, 'napis-'.$slot) : null;

        ($this->saveSettings)([MugOptions::LETTERING_PHOTOS[$slot] => $path]);

        MugPhotoFile::delete($previous);
    }
}
