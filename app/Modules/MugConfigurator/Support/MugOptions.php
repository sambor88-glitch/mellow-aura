<?php

namespace App\Modules\MugConfigurator\Support;

use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\SitePhoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * The mug configurator's settings from the panel, tidied up: sizes with prices, the glazes a customer picks,
 * the ink colour Kasia picks for her photo, where the text sits and how much of it fits.
 */
class MugOptions
{
    /** Kasia's photo from zdjecia/, until she uploads the side photo of a plain mug. */
    public const DEFAULT_PHOTO = 'zdjecia/kubek-cappuccino.webp';

    public const MAX_LINES = 6;

    public const MAX_CHARS_PER_LINE = 30;

    public function __construct(private Settings $settings) {}

    /**
     * Sizes for the page's currency and language. „label” is the size's name in the panel, which the basket keeps
     * whatever the page; „title” is that name for this page, „name” the title with the capacity, and „price” what
     * she pays here. A euro
     * page offers only the sizes with a euro price and an English name (mug-configurator::mug.sizes).
     *
     * @return Collection<int, array{label: string, title: string, capacity_ml: ?int, price_gross: int, price_eur: ?int, price: int, name: string}>
     */
    public function sizes(): Collection
    {
        $home = Locales::currency() === Locales::defaultCurrency();

        return collect((array) $this->settings->get('mug_sizes', []))
            ->filter(fn (mixed $size) => is_array($size) && filled($size['label'] ?? null) && is_numeric($size['price_gross'] ?? null))
            ->filter(fn (array $size) => $home || (is_numeric($size['price_eur'] ?? null) && Lang::has('mug-configurator::mug.sizes.'.self::sizeKey($size['label']))))
            ->map(fn (array $size) => [...$size, 'title' => $home ? (string) $size['label'] : __('mug-configurator::mug.sizes.'.self::sizeKey($size['label']))])
            ->map(fn (array $size) => [
                'label' => (string) $size['label'],
                'title' => $size['title'],
                'capacity_ml' => is_numeric($size['capacity_ml'] ?? null) ? (int) $size['capacity_ml'] : null,
                'price_gross' => (int) $size['price_gross'],
                'price_eur' => is_numeric($size['price_eur'] ?? null) ? (int) $size['price_eur'] : null,
                'price' => (int) ($home ? $size['price_gross'] : $size['price_eur']),
                'name' => $size['title'].(is_numeric($size['capacity_ml'] ?? null) ? ' '.(int) $size['capacity_ml'].' ml' : ''),
            ])
            ->values();
    }

    /**
     * The size in an address, e.g. /kubek-z-napisem?rozmiar=maly for „Mały”.
     */
    public static function sizeKey(string $label): string
    {
        return Str::slug($label);
    }

    /**
     * The size chosen when the page opens: the one in the address, or the middle one, like the prototype.
     *
     * @return array{label: string, title: string, capacity_ml: ?int, price_gross: int, price_eur: ?int, price: int, name: string}|null
     */
    public function startSize(?string $key): ?array
    {
        $sizes = $this->sizes();

        return $sizes->first(fn (array $size) => $key !== null && self::sizeKey($size['label']) === $key)
            ?? $sizes->get(intdiv(max($sizes->count() - 1, 0), 2));
    }

    /**
     * The glazes, named in the language of the page; on a page in another language only those with a name in it.
     *
     * @return Collection<int, array{code: string, name: string, hex: string}>
     */
    public function glazes(): Collection
    {
        if (Locales::current() === Locales::default()) {
            return $this->palette('mug_glazes');
        }

        return $this->palette('mug_glazes')
            ->filter(fn (array $glaze) => Lang::has('mug-configurator::mug.glazes.'.$glaze['code']))
            ->map(fn (array $glaze) => [...$glaze, 'name' => __('mug-configurator::mug.glazes.'.$glaze['code'])])
            ->values();
    }

    /**
     * @return Collection<int, array{code: string, name: string, hex: string}>
     */
    public function inks(): Collection
    {
        return $this->palette('mug_ink_colors');
    }

    /**
     * @return array{code: string, name: string, hex: string}
     */
    public function ink(): array
    {
        return $this->inks()->firstWhere('code', $this->settings->get('mug_ink_color'))
            ?? $this->inks()->first()
            ?? ['code' => 'graphite', 'name' => 'Grafit', 'hex' => '#2F2620'];
    }

    public function maxLines(): int
    {
        return min(self::MAX_LINES, max(1, (int) $this->settings->get('mug_max_lines', 3)));
    }

    public function maxCharsPerLine(): int
    {
        return min(self::MAX_CHARS_PER_LINE, max(4, (int) $this->settings->get('mug_max_chars_per_line', 16)));
    }

    /**
     * Where the text sits on the photo: centre in percent of the photo, letter size in percent, tilt in degrees.
     *
     * @return array{x: int, y: int, size: int, rotation: int}
     */
    public function position(): array
    {
        $position = (array) $this->settings->get('mug_text_position', []);

        return [
            'x' => min(90, max(10, (int) ($position['x_percent'] ?? 50))),
            'y' => min(90, max(10, (int) ($position['y_percent'] ?? 50))),
            'size' => min(220, max(50, (int) ($position['size_percent'] ?? 100))),
            'rotation' => min(15, max(-15, (int) ($position['rotation_deg'] ?? 0))),
        ];
    }

    public function photoPath(): string
    {
        return (string) $this->settings->get('mug_configurator_image', self::DEFAULT_PHOTO);
    }

    public function photoUrl(): ?string
    {
        return SitePhoto::url($this->photoPath());
    }

    /**
     * @return Collection<int, array{code: string, name: string, hex: string}>
     */
    private function palette(string $key): Collection
    {
        return collect((array) $this->settings->get($key, []))
            ->filter(fn (mixed $colour) => is_array($colour)
                && filled($colour['code'] ?? null)
                && filled($colour['name'] ?? null)
                && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($colour['hex'] ?? '')))
            ->map(fn (array $colour) => ['code' => (string) $colour['code'], 'name' => (string) $colour['name'], 'hex' => (string) $colour['hex']])
            ->values();
    }
}
