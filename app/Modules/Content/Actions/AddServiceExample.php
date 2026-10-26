<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Enums\Service;
use App\Modules\Content\Models\ServiceExample;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Adds a „przed i po” pair at the end of the service's list. Each photo is turned upright, scaled down
 * to 2400 px and saved again as WebP, which drops EXIF and the GPS position of the studio.
 */
class AddServiceExample
{
    private const LONGEST_SIDE = 2400;

    /**
     * @param  array{caption?: ?string, before_alt?: ?string, after_alt?: ?string}  $texts
     */
    public function __invoke(Service $service, UploadedFile $before, UploadedFile $after, array $texts): ServiceExample
    {
        // Both photos are converted first, so a photo that fails leaves no half-made pair behind.
        $webps = array_map(fn (UploadedFile $photo) => $this->webp($photo), ['before' => $before, 'after' => $after]);

        $example = ServiceExample::create([
            ...$texts,
            'service' => $service,
            'sort_order' => (int) ServiceExample::query()->where('service', $service)->max('sort_order') + 1,
        ]);

        foreach ($webps as $collection => $webp) {
            $example->addMedia($webp)
                ->usingFileName($service->slug().'-'.($collection === 'before' ? 'przed' : 'po').'-'.Str::lower(Str::random(6)).'.webp')
                ->toMediaCollection($collection);
        }

        return $example;
    }

    private function webp(UploadedFile $photo): string
    {
        $webp = tempnam(sys_get_temp_dir(), 'service');

        Image::useImageDriver('gd')
            ->loadFile($photo->getRealPath())
            ->fit(Fit::Max, self::LONGEST_SIDE, self::LONGEST_SIDE)
            ->quality(85)
            ->format('webp')
            ->save($webp);

        return $webp;
    }
}
