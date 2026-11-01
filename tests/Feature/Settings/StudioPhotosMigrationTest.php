<?php

namespace Tests\Feature\Settings;

use App\Modules\Content\Support\PagePhotos;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The studio's hero and gallery move from their own settings into Zdjęcia stron (MA-349).
 */
class StudioPhotosMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const SEEDED_GALLERY = [
        ['path' => 'zdjecia/kubek-ochujeje.webp', 'alt' => 'Kubek z wbijanym tekstem'],
        ['path' => 'zdjecia/zestaw-filizanka-talerz.webp', 'alt' => 'Filiżanka z talerzykiem'],
        ['path' => 'zdjecia/podstawki-muszle-roz.webp', 'alt' => 'Różowe podstawki w kształcie muszli'],
        ['path' => 'zdjecia/kasia-talerz-zloto.webp', 'alt' => 'Kasia z talerzem zdobionym złotem'],
        ['path' => 'zdjecia/patera-czarna-kolce.webp', 'alt' => 'Czarna patera z kolcami'],
        ['path' => 'zdjecia/kubek-cappuccino.webp', 'alt' => 'Kubek malowany ręcznie'],
        ['path' => 'zdjecia/kadzielnica-pszczoly.webp', 'alt' => 'Kadzielnica ze złoconym kołnierzem'],
        ['path' => 'zdjecia/talerz-niebieski-odcisk.webp', 'alt' => 'Talerz z odciskiem rośliny'],
        ['path' => 'zdjecia/scrunchies-fiolet.webp', 'alt' => 'Jedwabne scrunchies'],
        ['path' => 'zdjecia/kubek-nie-powinnam.webp', 'alt' => 'Kubek z wbijanym napisem w dłoni'],
        ['path' => 'zdjecia/zestaw-flatlay.webp', 'alt' => 'Filiżanka na talerzyku z malowaną lawendą'],
    ];

    private function migrate(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_11_01_210000_move_the_studio_photos_into_page_photos.php');
        $migration->up();
        app(Settings::class)->refresh();
    }

    public function test_the_seeded_photos_are_the_places_own_so_nothing_is_saved_and_the_old_settings_go(): void
    {
        Setting::create(['key' => 'studio_hero_image', 'value' => 'zdjecia/zestaw-flatlay.webp']);
        Setting::create(['key' => 'studio_gallery', 'value' => self::SEEDED_GALLERY]);

        $this->migrate();

        $this->assertNull(Setting::find(PagePhotos::SETTING));
        $this->assertNull(Setting::find('studio_hero_image'));
        $this->assertNull(Setting::find('studio_gallery'));

        // The places start with the same photos and descriptions the pages had.
        foreach (self::SEEDED_GALLERY as $index => $photo) {
            $this->assertSame($photo['path'], PagePhotos::defaultPath('studio-gallery-'.($index + 1)));
            $this->assertSame($photo['alt'], PagePhotos::defaultAlt('studio-gallery-'.($index + 1)));
        }
        foreach (range(1, 4) as $n) {
            $this->assertSame(PagePhotos::defaultPath('studio-gallery-'.$n), PagePhotos::defaultPath('workshops-studio-'.$n));
            $this->assertSame(PagePhotos::defaultAlt('studio-gallery-'.$n), PagePhotos::defaultAlt('workshops-studio-'.$n));
        }
        $this->assertSame('zdjecia/zestaw-flatlay.webp', PagePhotos::defaultPath('studio-hero'));
        $this->assertSame('zdjecia/zestaw-flatlay.webp', PagePhotos::defaultPath('workshops-hero'));
    }

    public function test_a_different_photo_moves_into_its_place_and_other_places_keep_their_changes(): void
    {
        Setting::create(['key' => PagePhotos::SETTING, 'value' => ['about-main' => ['focus' => ['x' => 30, 'y' => 70]]]]);
        Setting::create(['key' => 'studio_hero_image', 'value' => 'page-photos/pracownia-abc123.webp']);
        Setting::create(['key' => 'studio_gallery', 'value' => [
            ['path' => 'zdjecia/kubek-ochujeje.webp', 'alt' => 'Kubek z wbijanym tekstem'],
            ['path' => '', 'alt' => 'Bez zdjęcia — pomijane'],
            ['path' => 'zdjecia/misa-laguna.webp', 'alt' => 'Misa w kolorze laguny'],
            ['path' => 'zdjecia/podstawki-muszle-roz.webp', 'alt' => 'Podstawki na stole'],
        ]]);

        $this->migrate();

        $this->assertEquals([
            'about-main' => ['focus' => ['x' => 30, 'y' => 70]],
            'workshops-hero' => ['path' => 'page-photos/pracownia-abc123.webp'],
            'studio-hero' => ['path' => 'page-photos/pracownia-abc123.webp'],
            'studio-gallery-2' => ['path' => 'zdjecia/misa-laguna.webp', 'alt' => 'Misa w kolorze laguny'],
            'studio-gallery-3' => ['alt' => 'Podstawki na stole'],
            'workshops-studio-2' => ['path' => 'zdjecia/misa-laguna.webp', 'alt' => 'Misa w kolorze laguny'],
            'workshops-studio-3' => ['alt' => 'Podstawki na stole'],
            'workshops-studio-4' => ['path' => null],
            ...collect(range(4, 11))->mapWithKeys(fn (int $n) => ['studio-gallery-'.$n => ['path' => null]]),
        ], Setting::find(PagePhotos::SETTING)->value);
    }

    public function test_without_the_old_settings_nothing_changes(): void
    {
        $this->migrate();

        $this->assertNull(Setting::find(PagePhotos::SETTING));
    }

    public function test_the_seeder_no_longer_brings_them_back(): void
    {
        $seeded = file_get_contents(base_path('app/Modules/Settings/Database/Seeders/settings.json'));

        $this->assertStringNotContainsString('"studio_hero_image"', $seeded);
        $this->assertStringNotContainsString('"studio_gallery"', $seeded);
    }
}
