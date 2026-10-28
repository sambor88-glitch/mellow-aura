<?php

namespace App\Modules\Content\Support;

use App\Modules\Localization\Support\Locales;
use App\Modules\Shared\Support\FocalPoint;
use Illuminate\Support\HtmlString;

/**
 * One place of PagePhotos as a page shows it. A null url is an empty place: the page leaves the photo out.
 */
final readonly class PagePhoto
{
    /**
     * @param  array{x: int, y: int}  $focus
     * @param  ?array{x: int, y: int}  $phoneFocus  only for a place with a second point for a phone
     * @param  bool  $isDefault  nothing changed in the panel: the photo from zdjecia/ with the design's frame
     * @param  bool  $isReplaced  Kasia's own photo or no photo instead of the one from zdjecia/
     */
    public function __construct(
        public string $key,
        public ?string $path,
        public ?string $url,
        public array $focus,
        public ?array $phoneFocus,
        public ?string $alt,
        public bool $isDefault,
        public bool $isReplaced,
    ) {}

    public function exists(): bool
    {
        return $this->url !== null;
    }

    /** Smaller copies of the photo for a phone (PagePhotoSizes), null when there are none. */
    public function srcset(): ?string
    {
        return $this->path !== null && $this->url !== null ? app(PagePhotoSizes::class)->srcset($this->path, $this->url) : null;
    }

    /**
     * srcset and sizes for the <img>, or nothing when the photo has no smaller copies.
     *
     * @param  string  $sizes  how wide the photo shows, as in the sizes attribute
     */
    public function srcsetAttributes(string $sizes): HtmlString
    {
        $srcset = $this->srcset();

        return new HtmlString($srcset === null ? '' : 'srcset="'.e($srcset).'" sizes="'.e($sizes).'"');
    }

    /** The inline style for an <img class="object-cover">. */
    public function style(): string
    {
        return FocalPoint::css($this->focus['x'], $this->focus['y']);
    }

    /**
     * Both points as CSS variables, for a place that reads `--focus` and, on a phone, `--focus-phone`.
     */
    public function styleVariables(): string
    {
        $phone = $this->phoneFocus ?? $this->focus;

        return '--focus: '.$this->focus['x'].'% '.$this->focus['y'].'%; --focus-phone: '.$phone['x'].'% '.$phone['y'].'%';
    }

    /**
     * Kasia's description of her photo, written in Polish in the panel; an English page keeps its own text.
     */
    public function alt(string $default): string
    {
        return $this->alt !== null && Locales::current() === Locales::default() ? $this->alt : $default;
    }
}
