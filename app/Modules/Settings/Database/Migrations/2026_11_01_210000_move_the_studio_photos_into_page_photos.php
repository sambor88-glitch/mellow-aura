<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The hero and the gallery of /pracownia and /warsztaty-ceramiczne-krakow were two settings with no screen in the panel
 * (`studio_hero_image`, `studio_gallery`). Now they are places of Zdjęcia stron (PagePhotos, MA-349), which start with
 * the same photos. Whatever differs from them moves into `page_photos`, so the pages stay as they were, and the two old
 * settings go. The places are written out here, so this keeps working when PagePhotos changes later.
 */
return new class extends Migration
{
    private const HERO = 'zdjecia/zestaw-flatlay.webp';

    private const HEROES = ['workshops-hero', 'studio-hero'];

    /** The gallery as seeded: place on /pracownia => [path, alt]. The last place starts empty. */
    private const STUDIO = [
        'studio-gallery-1' => ['zdjecia/kubek-ochujeje.webp', 'Kubek z wbijanym tekstem'],
        'studio-gallery-2' => ['zdjecia/zestaw-filizanka-talerz.webp', 'Filiżanka z talerzykiem'],
        'studio-gallery-3' => ['zdjecia/podstawki-muszle-roz.webp', 'Różowe podstawki w kształcie muszli'],
        'studio-gallery-4' => ['zdjecia/kasia-talerz-zloto.webp', 'Kasia z talerzem zdobionym złotem'],
        'studio-gallery-5' => ['zdjecia/patera-czarna-kolce.webp', 'Czarna patera z kolcami'],
        'studio-gallery-6' => ['zdjecia/kubek-cappuccino.webp', 'Kubek malowany ręcznie'],
        'studio-gallery-7' => ['zdjecia/kadzielnica-pszczoly.webp', 'Kadzielnica ze złoconym kołnierzem'],
        'studio-gallery-8' => ['zdjecia/talerz-niebieski-odcisk.webp', 'Talerz z odciskiem rośliny'],
        'studio-gallery-9' => ['zdjecia/scrunchies-fiolet.webp', 'Jedwabne scrunchies'],
        'studio-gallery-10' => ['zdjecia/kubek-nie-powinnam.webp', 'Kubek z wbijanym napisem w dłoni'],
        'studio-gallery-11' => ['zdjecia/zestaw-flatlay.webp', 'Filiżanka na talerzyku z malowaną lawendą'],
        'studio-gallery-12' => [null, null],
    ];

    /** The workshops page showed the first four photos of the same gallery. */
    private const WORKSHOPS = ['workshops-studio-1', 'workshops-studio-2', 'workshops-studio-3', 'workshops-studio-4'];

    public function up(): void
    {
        $old = DB::table('settings')->whereIn('key', ['studio_hero_image', 'studio_gallery'])->pluck('value', 'key')
            ->map(fn (?string $value) => $value === null ? null : json_decode($value, true));

        if ($old->isEmpty()) {
            return;
        }

        $photos = $this->pagePhotos();

        if ($old->has('studio_hero_image') && $old->get('studio_hero_image') !== self::HERO) {
            $hero = $old->get('studio_hero_image');

            foreach (self::HEROES as $place) {
                $photos[$place] = ['path' => is_string($hero) && $hero !== '' ? $hero : null];
            }
        }

        if ($old->has('studio_gallery')) {
            $gallery = array_values(array_filter((array) $old->get('studio_gallery'),
                fn (mixed $photo) => is_array($photo) && is_string($photo['path'] ?? null) && $photo['path'] !== ''));
            $studio = array_values(self::STUDIO);

            foreach (array_keys(self::STUDIO) as $index => $place) {
                $photos[$place] = $this->difference($gallery[$index] ?? null, ...$studio[$index]);
            }

            foreach (self::WORKSHOPS as $index => $place) {
                $photos[$place] = $this->difference($gallery[$index] ?? null, ...$studio[$index]);
            }
        }

        $photos = array_filter($photos, fn (array $changes) => $changes !== []);

        if ($photos !== []) {
            DB::table('settings')->updateOrInsert(['key' => 'page_photos'], ['value' => json_encode($photos), 'updated_at' => now()]);
            DB::table('settings')->where('key', 'page_photos')->whereNull('created_at')->update(['created_at' => now()]);
        }

        DB::table('settings')->whereIn('key', ['studio_hero_image', 'studio_gallery'])->delete();
    }

    public function down(): void
    {
        // Kasia may have changed the photos in the panel since; the seeder brings back the old settings if needed.
    }

    /**
     * What a place keeps in `page_photos` so it shows the old photo: nothing when that is its own photo.
     *
     * @param  ?array{path: string, alt?: mixed}  $photo
     * @return array<string, mixed>
     */
    private function difference(?array $photo, ?string $path, ?string $alt): array
    {
        if ($photo === null) {
            return $path === null ? [] : ['path' => null];
        }

        $alt = $photo['path'] === $path ? $alt : null;
        $description = is_string($photo['alt'] ?? null) ? trim($photo['alt']) : '';

        return [
            ...($photo['path'] === $path ? [] : ['path' => $photo['path']]),
            ...($description === '' || $description === $alt ? [] : ['alt' => $description]),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function pagePhotos(): array
    {
        $saved = DB::table('settings')->where('key', 'page_photos')->value('value');
        $saved = is_string($saved) ? json_decode($saved, true) : null;

        return is_array($saved) ? array_filter($saved, 'is_array') : [];
    }
};
