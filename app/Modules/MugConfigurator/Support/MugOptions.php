<?php

namespace App\Modules\MugConfigurator\Support;

use App\Modules\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;

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
     * @return Collection<int, array{label: string, capacity_ml: ?int, price_gross: int, name: string}>
     */
    public function sizes(): Collection
    {
        return collect((array) $this->settings->get('mug_sizes', []))
            ->filter(fn (mixed $size) => is_array($size) && filled($size['label'] ?? null) && is_numeric($size['price_gross'] ?? null))
            ->map(fn (array $size) => [
                'label' => (string) $size['label'],
                'capacity_ml' => is_numeric($size['capacity_ml'] ?? null) ? (int) $size['capacity_ml'] : null,
                'price_gross' => (int) $size['price_gross'],
                'name' => $size['label'].(is_numeric($size['capacity_ml'] ?? null) ? ' '.(int) $size['capacity_ml'].' ml' : ''),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{code: string, name: string, hex: string}>
     */
    public function glazes(): Collection
    {
        return $this->palette('mug_glazes');
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

    /**
     * A photo from zdjecia/ comes with the site's build; a photo uploaded in the panel lives on the public disk.
     */
    public function photoUrl(): ?string
    {
        $path = $this->photoPath();

        if (str_starts_with($path, 'zdjecia/')) {
            return is_file(base_path($path)) ? Vite::asset($path) : null;
        }

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
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
