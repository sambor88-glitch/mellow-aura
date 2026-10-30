<?php

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\MailTexts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Puts Kasia's picture above the signature of every customer mail (MA-287), or takes it off with null.
 * The picture is scaled down to fit twice the box it shows in, so it stays sharp on a phone, laid on the cream of the
 * mail card, so a see-through PNG with dark ink does not vanish in a mail app's dark mode, and saved again as PNG,
 * which drops EXIF and the GPS position of the studio. WebP is not an option: Outlook does not show it.
 * Every upload gets a new name, because Gmail keeps its own copy of a picture by the picture's address.
 * The picture it replaces is deleted.
 */
class ReplaceMailSignatureImage
{
    public const DIRECTORY = 'mail';

    /** The box the picture shows in, in CSS pixels: a handwritten name fills its width, a square logo its height. */
    private const WIDTH = 200;

    private const HEIGHT = 80;

    /** The mail card's background in shared::components.mail.layout. */
    private const BACKGROUND = '#FCF9F4';

    public function __construct(private Settings $settings, private SaveSettings $saveSettings) {}

    public function __invoke(?UploadedFile $image): void
    {
        $previous = $this->settings->get(MailTexts::SIGNATURE_IMAGE)['path'] ?? null;

        ($this->saveSettings)([MailTexts::SIGNATURE_IMAGE => $image === null ? null : $this->store($image)]);

        if (is_string($previous) && str_starts_with($previous, self::DIRECTORY.'/')) {
            Storage::disk('public')->delete($previous);
        }
    }

    /**
     * @return array{path: string, width: int, height: int} the width and height it shows at, half of the file's
     */
    private function store(UploadedFile $image): array
    {
        $png = tempnam(sys_get_temp_dir(), 'signature');

        Image::useImageDriver('gd')
            ->loadFile($image->getRealPath())
            ->fit(Fit::Max, self::WIDTH * 2, self::HEIGHT * 2)
            ->background(self::BACKGROUND)
            ->format('png')
            ->save($png);

        [$width, $height] = getimagesize($png);
        $path = self::DIRECTORY.'/signature-'.Str::lower(Str::random(8)).'.png';
        Storage::disk('public')->put($path, (string) file_get_contents($png));
        @unlink($png);

        return ['path' => $path, 'width' => max(1, intdiv($width, 2)), 'height' => max(1, intdiv($height, 2))];
    }
}
