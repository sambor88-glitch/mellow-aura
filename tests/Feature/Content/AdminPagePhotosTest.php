<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Modules\Content\Support\PagePhotos;
use App\Modules\Content\Support\PagePhotoSizes;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPagePhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->owner = User::factory()->create();
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/panel/zdjecia-stron')->assertRedirect('/panel/logowanie');
        $this->put('/panel/zdjecia-stron/home-hero')->assertRedirect('/panel/logowanie');
        $this->delete('/panel/zdjecia-stron/home-hero')->assertRedirect('/panel/logowanie');
    }

    public function test_every_place_has_its_photo_from_zdjecia_and_the_hero_a_second_point_for_a_phone(): void
    {
        foreach (PagePhotos::keys() as $key) {
            $this->assertFileExists(base_path(PagePhotos::defaultPath($key)), $key);
        }

        $this->assertCount(25, PagePhotos::keys());

        $this->actingAs($this->owner)
            ->get('/panel/zdjecia-stron')
            ->assertOk()
            ->assertSee('>Zdjęcia stron</a>', false)
            ->assertSeeInOrder(['Strona główna', 'Na samej górze', 'name="focus[y]" min="0" max="100" step="1" value="82"', 'Na telefonie ramka jest węższa', 'name="focus_phone[y]" min="0" max="100" step="1" value="82"', 'O mnie', 'Kontakt', 'Dla lokali', 'Usługi', 'Zamówienia indywidualne'], false)
            ->assertSee('name="focus_phone[x]"', false)
            ->assertSee('name="focus[x]" min="0" max="100" step="5" value="50"', false)
            ->assertDontSee('Przywróć pierwsze zdjęcie');

        $this->assertSame(1, substr_count($this->get('/panel/zdjecia-stron')->getContent(), 'name="focus_phone[x]"'));
    }

    public function test_the_point_and_the_description_are_saved_and_shown_on_the_page(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/zdjecia-stron/about-main', ['focus' => ['x' => 30, 'y' => 70], 'alt' => '  Kasia przy kole  '])
            ->assertRedirect('/panel/zdjecia-stron#about-main')
            ->assertSessionHas('panel_status', 'Kadr zapisany. Klienci już go widzą.');

        $this->assertEquals(['about-main' => ['focus' => ['x' => 30, 'y' => 70], 'alt' => 'Kasia przy kole']], Setting::find(PagePhotos::SETTING)->value);

        $this->get('/o-mnie')
            ->assertOk()
            ->assertSee('alt="Kasia przy kole"', false)
            ->assertSee('style="object-position: 30% 70%"', false);
        $this->get('/panel/zdjecia-stron')->assertSeeInOrder(['id="about-main"', 'Przywróć pierwszy kadr'], false)->assertDontSee('Przywróć pierwsze zdjęcie');

        $this->post('/panel/zdjecia-stron/about-main/przywroc')->assertRedirect('/panel/zdjecia-stron#about-main');
        $this->assertEquals([], Setting::find(PagePhotos::SETTING)->value);
    }

    public function test_the_hero_keeps_two_points_and_a_decorative_place_no_description(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/zdjecia-stron/home-hero', ['focus' => ['x' => 50, 'y' => 60], 'focus_phone' => ['x' => 40, 'y' => 90]])
            ->assertRedirect('/panel/zdjecia-stron#home-hero');
        $this->put('/panel/zdjecia-stron/home-float-1', ['focus' => ['x' => 20, 'y' => 20], 'focus_phone' => ['x' => 10, 'y' => 10], 'alt' => 'Misa']);

        // MySQL keeps JSON keys in its own order, so the order is not compared.
        $this->assertEquals([
            'home-hero' => ['focus' => ['x' => 50, 'y' => 60], 'focus_phone' => ['x' => 40, 'y' => 90]],
            'home-float-1' => ['focus' => ['x' => 20, 'y' => 20]],
        ], Setting::find(PagePhotos::SETTING)->value);

        $this->get('/')->assertOk()->assertSee('style="--focus: 50% 60%; --focus-phone: 40% 90%"', false);
    }

    public function test_a_wrong_point_stays_at_its_place(): void
    {
        $this->actingAs($this->owner)
            ->from('/panel/zdjecia-stron')
            ->put('/panel/zdjecia-stron/contact', ['focus' => ['x' => 150, 'y' => 20]])
            ->assertRedirect('/panel/zdjecia-stron#contact')
            ->assertSessionHasErrors(['focus.x' => 'Przesuń suwak kadru jeszcze raz'], errorBag: 'zdjecie-contact');

        $this->assertNull(Setting::find(PagePhotos::SETTING));
        $this->put('/panel/zdjecia-stron/nie-ma-takiego', ['focus' => ['x' => 50, 'y' => 50]])->assertNotFound();
    }

    public function test_kasia_puts_in_her_photo_takes_it_off_and_brings_back_the_first_one(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)
            ->put('/panel/zdjecia-stron/b2b', ['focus' => ['x' => 10, 'y' => 10], 'alt' => 'Stary opis']);
        $this->post('/panel/zdjecia-stron/b2b', ['photo' => UploadedFile::fake()->image('IMG_2451.jpg', 1600, 1200)])
            ->assertRedirect('/panel/zdjecia-stron#b2b')
            ->assertSessionHas('panel_status', 'Zdjęcie wgrane. Klienci już je widzą — ustaw kadr, jeśli trzeba.');

        // The old point and description were for the old photo.
        $path = Setting::find(PagePhotos::SETTING)->value['b2b']['path'];
        $this->assertEquals(['b2b' => ['path' => $path]], Setting::find(PagePhotos::SETTING)->value);
        $this->assertMatchesRegularExpression('#^page-photos/b2b-[a-z0-9]{6}\.webp$#', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('image/webp', Storage::disk('public')->mimeType($path));

        $this->get('/ceramika-dla-gastronomii')->assertOk()->assertSee('src="'.Storage::disk('public')->url($path).'"', false);
        // A phone gets a smaller copy of the 1600 px photo (MA-232).
        $copies = Storage::disk('public')->files(PagePhotoSizes::DIRECTORY);
        $this->assertCount(4, $copies);
        $this->get('/ceramika-dla-gastronomii')->assertSee(Storage::disk('public')->url($copies[1]).' 400w', false)
            ->assertSee(Storage::disk('public')->url($path).' 1600w', false);
        $this->get('/panel/zdjecia-stron')->assertSee('Przywróć pierwsze zdjęcie');

        $this->delete('/panel/zdjecia-stron/b2b')
            ->assertRedirect('/panel/zdjecia-stron#b2b')
            ->assertSessionHas('panel_status', 'Zdjęcie zdjęte ze strony. Jego miejsce zostaje puste.');
        Storage::disk('public')->assertMissing($path);
        $this->assertSame([], Storage::disk('public')->files(PagePhotoSizes::DIRECTORY));
        $this->assertEquals(['b2b' => ['path' => null]], Setting::find(PagePhotos::SETTING)->value);
        $this->get('/ceramika-dla-gastronomii')->assertOk()->assertDontSee('Kawa w ręcznie robionym kubku');
        $this->get('/panel/zdjecia-stron')->assertSee('Brak zdjęcia — to miejsce na stronie jest puste');

        $this->post('/panel/zdjecia-stron/b2b/przywroc')
            ->assertRedirect('/panel/zdjecia-stron#b2b')
            ->assertSessionHas('panel_status', 'Wróciło pierwsze zdjęcie z pierwszym kadrem.');
        $this->assertEquals([], Setting::find(PagePhotos::SETTING)->value);
        $this->get('/ceramika-dla-gastronomii')->assertOk()->assertSee('alt="Kawa w ręcznie robionym kubku"', false);
    }

    public function test_a_file_that_is_not_a_photo_is_refused_at_its_place(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)
            ->post('/panel/zdjecia-stron/custom-orders-2', ['photo' => UploadedFile::fake()->create('pomysl.pdf', 100, 'application/pdf')])
            ->assertRedirect('/panel/zdjecia-stron#custom-orders-2')
            ->assertSessionHasErrors(['photo' => 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP'], errorBag: 'zdjecie-custom-orders-2');

        $this->assertNull(Setting::find(PagePhotos::SETTING));
    }

    public function test_an_empty_place_leaves_the_photo_out_of_each_page(): void
    {
        Setting::query()->updateOrCreate(['key' => PagePhotos::SETTING], ['value' => collect(PagePhotos::keys())->mapWithKeys(fn (string $key) => [$key => ['path' => null]])->all()]);

        foreach (['/', '/o-mnie', '/kontakt', '/ceramika-dla-gastronomii', '/z-twojej-apaszki', '/zamowienia-indywidualne'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('zdjecia/', false)->assertDontSee('object-position: 50% 50%', false);
        }
    }
}
