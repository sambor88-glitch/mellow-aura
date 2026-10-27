<?php

namespace App\Modules\Content\Support;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\FocalPoint;
use App\Modules\Shared\Support\SitePhoto;

/**
 * The photos of the shop's own pages, each in its place: the hero, the arch next to „Kasia”, the cards of the services.
 * Every place starts with its photo from zdjecia/ and its frame from the Aura design; in the panel („Zdjęcia stron”) Kasia
 * puts in her own photo, takes the photo off the page or moves the point that stays in view (FocalPoint).
 *
 * The setting `page_photos` keeps only what she changed, by place: `path` (a photo on the public disk, or null for no
 * photo at all), `focus`, `focus_phone` and `alt`. A place she never touched has no entry, so the page looks like the design.
 */
class PagePhotos
{
    public const SETTING = 'page_photos';

    public const DIRECTORY = 'page-photos';

    /**
     * Places by page. `frames` are the shapes of the place for the preview in the panel; `phone` gives the place a second
     * point for a phone, where the shape of the place changes most; `decorative` places have alt="" and no description.
     *
     * @var array<string, array{page: string, label: string, file: string, frames: array<string, ?string>, focus?: array{int, int}, phone?: bool, decorative?: bool}>
     */
    private const PLACES = [
        'home-hero' => ['page' => 'Strona główna', 'label' => 'Na samej górze — zdjęcie w ramce, które otwiera się przy przewijaniu', 'file' => 'kasia-talerz-zloto.webp', 'frames' => ['aspect-[3/4]' => 'na starcie', 'aspect-[16/10]' => 'otwarta'], 'focus' => [50, 82], 'phone' => true],
        'home-float-1' => ['page' => 'Strona główna', 'label' => 'Wokół ramki — 1., u góry z lewej', 'file' => 'misa-laguna.webp', 'frames' => ['aspect-[3/4]' => null], 'decorative' => true],
        'home-float-2' => ['page' => 'Strona główna', 'label' => 'Wokół ramki — 2., u góry z prawej', 'file' => 'podstawki-muszle-roz.webp', 'frames' => ['aspect-square' => null], 'decorative' => true],
        'home-float-3' => ['page' => 'Strona główna', 'label' => 'Wokół ramki — 3., mała u góry (tylko na komputerze)', 'file' => 'opaska-jedwab.webp', 'frames' => ['aspect-[3/4]' => null], 'decorative' => true],
        'home-float-4' => ['page' => 'Strona główna', 'label' => 'Wokół ramki — 4., na dole z lewej', 'file' => 'kubek-cappuccino.webp', 'frames' => ['aspect-square' => null], 'decorative' => true],
        'home-float-5' => ['page' => 'Strona główna', 'label' => 'Wokół ramki — 5., na dole z prawej', 'file' => 'wazon-rzezbiony.webp', 'frames' => ['aspect-[3/4]' => null], 'decorative' => true],
        'home-float-6' => ['page' => 'Strona główna', 'label' => 'Wokół ramki — 6., mała na dole (tylko na komputerze)', 'file' => 'kadzielnica-pszczoly.webp', 'frames' => ['aspect-square' => null], 'decorative' => true],
        'home-kasia' => ['page' => 'Strona główna', 'label' => 'Przy „Kasia” — zdjęcie w łuku', 'file' => 'zestaw-filizanka-talerz.webp', 'frames' => ['aspect-[3/4]' => null]],
        'home-gifts-1' => ['page' => 'Strona główna', 'label' => 'Prezenty — lewe zdjęcie', 'file' => 'kubek-cappuccino.webp', 'frames' => ['aspect-square' => null]],
        'home-gifts-2' => ['page' => 'Strona główna', 'label' => 'Prezenty — prawe zdjęcie', 'file' => 'scrunchies-fiolet.webp', 'frames' => ['aspect-square' => null]],
        'home-scarf' => ['page' => 'Strona główna', 'label' => 'Karta „Z Twojej apaszki”', 'file' => 'opaska-jedwab.webp', 'frames' => ['aspect-[4/5]' => null]],
        'home-imprint' => ['page' => 'Strona główna', 'label' => 'Karta „Odcisk Twojej rośliny”', 'file' => 'talerz-niebieski-odcisk.webp', 'frames' => ['aspect-[4/5]' => null]],
        'home-workshops-1' => ['page' => 'Strona główna', 'label' => 'Warsztaty — duże zdjęcie z lewej', 'file' => 'zestaw-flatlay.webp', 'frames' => ['aspect-[3/4]' => null], 'decorative' => true],
        'home-workshops-2' => ['page' => 'Strona główna', 'label' => 'Warsztaty — u góry z prawej', 'file' => 'patera-czarna-kolce.webp', 'frames' => ['aspect-[3/4]' => null], 'decorative' => true],
        'home-workshops-3' => ['page' => 'Strona główna', 'label' => 'Warsztaty — na dole z prawej', 'file' => 'kubek-nie-powinnam.webp', 'frames' => ['aspect-[7/8]' => null], 'decorative' => true],
        'about-main' => ['page' => 'O mnie', 'label' => 'Duże zdjęcie', 'file' => 'kasia-talerz-zloto.webp', 'frames' => ['aspect-[3/4]' => null]],
        'about-detail-1' => ['page' => 'O mnie', 'label' => 'Pod spodem — lewe', 'file' => 'kubek-ochujeje.webp', 'frames' => ['aspect-square' => null]],
        'about-detail-2' => ['page' => 'O mnie', 'label' => 'Pod spodem — prawe', 'file' => 'kadzielnica-pszczoly.webp', 'frames' => ['aspect-square' => null]],
        'contact' => ['page' => 'Kontakt', 'label' => 'Pod danymi kontaktowymi', 'file' => 'talerz-niebieski-odcisk.webp', 'frames' => ['aspect-[4/3]' => null]],
        'b2b' => ['page' => 'Dla lokali', 'label' => 'Obok wstępu', 'file' => 'kubek-cappuccino.webp', 'frames' => ['aspect-[4/5]' => null]],
        'service-scarf' => ['page' => 'Usługi', 'label' => '„Z Twojej apaszki” — obok wstępu', 'file' => 'opaska-jedwab.webp', 'frames' => ['aspect-[3/4]' => null]],
        'service-imprint' => ['page' => 'Usługi', 'label' => '„Odcisk Twojej rośliny” — obok wstępu', 'file' => 'talerz-niebieski-odcisk.webp', 'frames' => ['aspect-[3/4]' => null]],
        'custom-orders-1' => ['page' => 'Zamówienia indywidualne', 'label' => 'Pod krokami — pierwsze', 'file' => 'zestaw-filizanka-talerz.webp', 'frames' => ['aspect-square' => null]],
        'custom-orders-2' => ['page' => 'Zamówienia indywidualne', 'label' => 'Pod krokami — drugie', 'file' => 'kubek-krolowa-matka.webp', 'frames' => ['aspect-square' => null]],
        'custom-orders-3' => ['page' => 'Zamówienia indywidualne', 'label' => 'Pod krokami — trzecie (na telefonie szerokie)', 'file' => 'podstawki-muszle-roz.webp', 'frames' => ['aspect-square' => 'komputer', 'aspect-[2/1]' => 'telefon']],
    ];

    public function __construct(private Settings $settings) {}

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::PLACES);
    }

    /**
     * @return array{page: string, label: string, file: string, frames: array<string, ?string>, focus?: array{int, int}, phone?: bool, decorative?: bool}
     */
    public static function place(string $key): array
    {
        return self::PLACES[$key] ?? throw new \InvalidArgumentException('No page photo place '.$key);
    }

    /** The photo from zdjecia/ the place starts with. */
    public static function defaultPath(string $key): string
    {
        return 'zdjecia/'.self::place($key)['file'];
    }

    public function get(string $key): PagePhoto
    {
        $place = self::place($key);
        $saved = $this->saved($key);
        [$x, $y] = $place['focus'] ?? [FocalPoint::CENTRE, FocalPoint::CENTRE];
        $focus = fn (mixed $point) => is_array($point) ? ['x' => (int) ($point['x'] ?? $x), 'y' => (int) ($point['y'] ?? $y)] : ['x' => $x, 'y' => $y];
        $path = array_key_exists('path', $saved) ? $saved['path'] : self::defaultPath($key);

        return new PagePhoto(
            key: $key,
            path: is_string($path) && $path !== '' ? $path : null,
            url: SitePhoto::url(is_string($path) ? $path : null),
            focus: $focus($saved['focus'] ?? null),
            phoneFocus: ($place['phone'] ?? false) ? $focus($saved['focus_phone'] ?? $saved['focus'] ?? null) : null,
            alt: is_string($saved['alt'] ?? null) && $saved['alt'] !== '' ? $saved['alt'] : null,
            isDefault: $saved === [],
            isReplaced: array_key_exists('path', $saved),
        );
    }

    /**
     * What Kasia changed in one place, merged into the setting by the caller through SaveSettings.
     *
     * @param  array<string, mixed>  $changes  keys `path`, `focus`, `focus_phone`, `alt`; null as a value of
     *                                         `focus`, `focus_phone` or `alt` drops it, while `path` => null means no photo
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function with(string $key, array $changes): array
    {
        self::place($key);
        $all = (array) $this->settings->get(self::SETTING, []);
        $saved = [...$this->saved($key), ...$changes];

        foreach (['focus', 'focus_phone', 'alt'] as $field) {
            if (array_key_exists($field, $saved) && $saved[$field] === null) {
                unset($saved[$field]);
            }
        }

        if ($saved === []) {
            unset($all[$key]);
        } else {
            $all[$key] = $saved;
        }

        return [self::SETTING => $all];
    }

    /**
     * What a place looks like without the parts Kasia changed: `path` dropped brings back the photo from zdjecia/.
     *
     * @param  list<string>  $fields
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function without(string $key, array $fields): array
    {
        $saved = $this->saved($key);

        foreach ($fields as $field) {
            unset($saved[$field]);
        }

        $all = (array) $this->settings->get(self::SETTING, []);
        $all[$key] = $saved;

        if ($saved === []) {
            unset($all[$key]);
        }

        return [self::SETTING => $all];
    }

    /** @return array<string, mixed> */
    private function saved(string $key): array
    {
        $saved = ((array) $this->settings->get(self::SETTING, []))[$key] ?? [];

        return is_array($saved) ? $saved : [];
    }
}
