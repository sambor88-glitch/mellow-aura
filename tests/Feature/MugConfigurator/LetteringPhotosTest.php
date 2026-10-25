<?php

namespace Tests\Feature\MugConfigurator;

use App\Models\User;
use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MA-113: the mug page says the text is stamped into the clay, not printed, and shows it — a close-up against the
 * light next to the description and a finished, fired text under the preview, both uploaded by Kasia in the panel.
 */
class LetteringPhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->owner = User::factory()->create();
    }

    public function test_the_page_speaks_of_stamping_and_the_dishwasher_before_any_photo_exists(): void
    {
        $this->get('/kubek-z-napisem')
            ->assertOk()
            ->assertSee('<title>Kubek z napisem na zamówienie — Twój tekst wbity w glinę</title>', false)
            ->assertSee('litery wbijane stemplem w glinę', false)
            ->assertSeeInOrder(['Wbijam litery stemplem', 'Napis wbity w glinę, <em class="text-brown italic">nie nadrukowany</em>', 'Każdą literę wbijam stemplem osobno', 'schodzą w zmywarce'], false)
            ->assertDontSee('napis-closeup')
            ->assertDontSee('Tak wygląda gotowy napis po wypale');
    }

    public function test_kasia_uploads_both_close_ups_and_the_page_and_google_show_them(): void
    {
        $this->actingAs($this->owner)
            ->get('/panel/kubek-z-napisem')
            ->assertOk()
            ->assertSeeInOrder(['Napis z bliska', 'Napis pod światło', 'Wybierz zdjęcie z telefonu albo komputera', 'Gotowy napis po wypale']);

        $this->actingAs($this->owner)
            ->post('/panel/kubek-z-napisem/z-bliska/closeup', ['photo' => UploadedFile::fake()->image('pod-swiatlo.jpg', 1600, 1200)])
            ->assertRedirect(route('admin.mug.edit').'#z-bliska');
        $this->actingAs($this->owner)
            ->post('/panel/kubek-z-napisem/z-bliska/fired', ['photo' => UploadedFile::fake()->image('po-wypale.png', 1200, 900)]);

        $options = app(MugOptions::class);
        $closeup = $options->letteringPhotoPath('closeup');
        $fired = $options->letteringPhotoPath('fired');
        $this->assertStringStartsWith('mug-configurator/napis-closeup-', $closeup);
        $this->assertStringEndsWith('.webp', $fired);
        Storage::disk('public')->assertExists([$closeup, $fired]);

        $page = $this->get('/kubek-z-napisem')->assertOk();
        $page->assertSeeInOrder([Storage::disk('public')->url($fired), 'Tak wygląda gotowy napis po wypale', 'Dodaj do koszyka'], false)
            ->assertSeeInOrder([Storage::disk('public')->url($closeup), 'Napis wbity w glinę, sfotografowany pod światło', 'Każdą literę wbijam stemplem osobno'], false);

        // The product's photos in JSON-LD: the mug, then both close-ups.
        preg_match('#<script type="application/ld\+json">(\{[^<]*"ProductGroup"[^<]*)</script>#', $page->getContent(), $match);
        $images = json_decode($match[1], true)['image'];
        $this->assertSame(array_values(array_filter([$options->photoUrl(), Storage::disk('public')->url($closeup), Storage::disk('public')->url($fired)])), $images);
    }

    public function test_a_new_photo_replaces_the_old_file_and_removing_it_empties_its_place(): void
    {
        $this->actingAs($this->owner)->post('/panel/kubek-z-napisem/z-bliska/closeup', ['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]);
        $first = app(MugOptions::class)->letteringPhotoPath('closeup');

        $this->actingAs($this->owner)->post('/panel/kubek-z-napisem/z-bliska/closeup', ['photo' => UploadedFile::fake()->image('b.jpg', 800, 600)]);
        app(Settings::class)->refresh();
        $second = app(MugOptions::class)->letteringPhotoPath('closeup');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($this->owner)->delete('/panel/kubek-z-napisem/z-bliska/closeup')->assertRedirect(route('admin.mug.edit').'#z-bliska');
        app(Settings::class)->refresh();
        $this->assertNull(app(MugOptions::class)->letteringPhotoPath('closeup'));
        Storage::disk('public')->assertMissing($second);
        $this->get('/kubek-z-napisem')->assertDontSee('sfotografowany pod światło');
    }

    public function test_only_a_photo_is_accepted_and_only_for_the_two_places(): void
    {
        $this->actingAs($this->owner)
            ->from('/panel/kubek-z-napisem')
            ->post('/panel/kubek-z-napisem/z-bliska/fired', ['photo' => UploadedFile::fake()->create('cennik.pdf', 20, 'application/pdf')])
            ->assertRedirect(route('admin.mug.edit').'#z-bliska')
            ->assertSessionHasErrorsIn('napis-z-bliska', ['photo' => 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP']);

        $this->actingAs($this->owner)->post('/panel/kubek-z-napisem/z-bliska/hero', ['photo' => UploadedFile::fake()->image('x.jpg')])->assertNotFound();
    }

    public function test_the_technique_text_is_kasias_to_change_and_empty_hides_the_section(): void
    {
        $texts = Setting::query()->where('key', 'like', 'text_mug_%')->pluck('value', 'key')->all();

        $this->actingAs($this->owner)
            ->put('/panel/kubek-z-napisem/teksty', [...$texts, 'text_mug_technique' => "Wbijam każdą literę.\nNie zejdzie w zmywarce."])
            ->assertRedirect();
        $this->get('/kubek-z-napisem')->assertSee("Wbijam każdą literę.\nNie zejdzie w zmywarce.");

        $this->actingAs($this->owner)->put('/panel/kubek-z-napisem/teksty', [...$texts, 'text_mug_technique' => '']);
        $this->get('/kubek-z-napisem')->assertDontSee('nie nadrukowany');
    }

    public function test_the_english_page_shows_the_photo_but_not_kasias_polish_text(): void
    {
        $this->actingAs($this->owner)->post('/panel/kubek-z-napisem/z-bliska/closeup', ['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]);

        $this->get('/en/custom-text-mug')
            ->assertOk()
            ->assertSee('Stamped into clay, <em class="text-brown italic">not printed</em>', false)
            ->assertSee('photographed against the light')
            ->assertDontSee('Każdą literę wbijam');
    }
}
