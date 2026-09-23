<?php

namespace Tests\Feature\MugConfigurator;

use App\Models\User;
use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMugTest extends TestCase
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
        $this->get('/panel/kubek-z-napisem')->assertRedirect('/panel/logowanie');
        $this->put('/panel/kubek-z-napisem/rozmiary')->assertRedirect('/panel/logowanie');
    }

    public function test_the_page_shows_the_settings_like_the_prototype_tab(): void
    {
        $this->actingAs($this->owner)
            ->get('/panel/kubek-z-napisem')
            ->assertOk()
            ->assertSee('href="'.route('admin.mug.edit').'"', false)
            ->assertSeeInOrder(['Zdjęcie kubka pod napisy', 'bez żadnego napisu', 'Wgraj inne zdjęcie kubka'])
            ->assertDontSee('Przywróć domyślne zdjęcie')
            ->assertSeeInOrder(['Rozmiary, pojemność i ceny', 'value="Mały"', 'value="200"', 'value="69"', 'value="Duży"', 'value="400"', 'value="95"', 'nowy rozmiar'], false)
            ->assertSeeInOrder(['Ile znaków wolno wpisać', 'name="max_chars_per_line" value="16"', 'name="max_lines" value="3"', 'Klientka wpisze najwyżej', '48'], false)
            ->assertSeeInOrder(['Gdzie ma siedzieć napis', 'TWÓJ NAPIS', 'Kolor napisu', 'value="graphite"', 'checked', 'name="x_percent"', 'value="70"', 'name="rotation_deg"', 'value="-2"'], false)
            ->assertSeeInOrder(['Teksty na stronie kubka', 'Kubek, który mówi to,'], false);
    }

    public function test_sizes_prices_and_the_text_limit_are_saved(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/kubek-z-napisem/rozmiary', [
                'sizes' => [
                    ['label' => 'Mały', 'capacity' => '250 ml', 'price' => '72,50', 'price_eur' => '19 €'],
                    ['label' => 'Średni', 'capacity' => '300', 'price' => '79', 'remove' => '1'],
                    ['label' => 'Duży', 'capacity' => '', 'price' => '99'],
                    ['label' => '', 'capacity' => '', 'price' => ''],
                ],
                'max_chars_per_line' => '18',
                'max_lines' => '2',
            ])
            ->assertRedirect('/panel/kubek-z-napisem#rozmiary')
            ->assertSessionHas('panel_status', 'Zapisane. Koszyk już liczy według nowych cen.');

        $this->assertEquals([
            ['label' => 'Mały', 'capacity_ml' => 250, 'price_gross' => 7250, 'price_eur' => 1900],
            ['label' => 'Duży', 'capacity_ml' => null, 'price_gross' => 9900, 'price_eur' => null],
        ], Setting::find('mug_sizes')->value);
        $this->assertSame([18, 2], [Setting::find('mug_max_chars_per_line')->value, Setting::find('mug_max_lines')->value]);

        $this->put('/panel/kubek-z-napisem/rozmiary', [
            'sizes' => [['label' => 'Mały', 'capacity' => 'dużo', 'price' => '70'], ['label' => 'mały', 'capacity' => '', 'price' => '']],
            'max_chars_per_line' => '31',
            'max_lines' => '0',
        ])->assertSessionHasErrorsIn('rozmiary', [
            'sizes.0.capacity' => 'Pojemność to liczba mililitrów, np. 300 — albo puste pole',
            'sizes.1.label' => 'Dwa rozmiary mają tę samą nazwę — klientka ich nie odróżni',
            'sizes.1.price' => 'Wpisz cenę, np. 79 albo 79,90',
            'max_chars_per_line' => 'Znaków w linii: od 4 do 30',
            'max_lines' => 'Liczba linii: od 1 do 6',
        ]);

        $this->put('/panel/kubek-z-napisem/rozmiary', ['sizes' => [['label' => 'Mały', 'price' => '70', 'remove' => '1']], 'max_chars_per_line' => '16', 'max_lines' => '3'])
            ->assertSessionHasErrorsIn('rozmiary', ['sizes' => 'Zostaw choć jeden rozmiar z ceną']);
    }

    public function test_the_position_and_ink_colour_are_saved(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/kubek-z-napisem/napis', ['x_percent' => '55', 'y_percent' => '60', 'size_percent' => '120', 'rotation_deg' => '-5', 'ink' => 'gold_24k'])
            ->assertRedirect('/panel/kubek-z-napisem#napis');

        $this->assertEquals(['x_percent' => 55, 'y_percent' => 60, 'size_percent' => 120, 'rotation_deg' => -5], Setting::find('mug_text_position')->value);
        $this->assertSame('gold_24k', Setting::find('mug_ink_color')->value);
        $this->get('/kubek-z-napisem')->assertSee('left: 55%; top: 60%; transform: translate(-50%, -50%) rotate(-5deg)', false)->assertSee('color: #A8813F', false);

        $this->put('/panel/kubek-z-napisem/napis', ['x_percent' => '95', 'y_percent' => '60', 'size_percent' => '120', 'rotation_deg' => '0', 'ink' => 'neon'])
            ->assertSessionHasErrorsIn('napis', ['x_percent' => 'Ustaw suwak jeszcze raz', 'ink' => 'Wybierz kolor napisu z palety']);
    }

    public function test_the_texts_are_saved_and_an_empty_one_leaves_the_page(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/kubek-z-napisem/teksty', [
                'text_mug_heading' => "Kubek, który mówi\r\nto, co myślisz",
                'text_mug_lead' => 'Wbijam litery stemplem.',
                'text_mug_note' => '',
                'text_mug_lead_time' => 'gotowe w 4 tygodnie',
                'text_mug_bulk_order' => '',
                'text_mug_refusals' => '',
            ])
            ->assertRedirect('/panel/kubek-z-napisem#teksty');

        $this->assertSame("Kubek, który mówi\nto, co myślisz", Setting::find('text_mug_heading')->value);

        $this->get('/kubek-z-napisem')
            ->assertSee('gotowe w 4 tygodnie')
            ->assertDontSee('Podgląd jest orientacyjny')
            ->assertDontSee('Zamawiasz więcej?');
    }

    public function test_a_new_photo_is_saved_as_webp_and_the_default_can_come_back(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)
            ->post('/panel/kubek-z-napisem/zdjecie', ['photo' => UploadedFile::fake()->image('IMG_2451.jpg', 1600, 1600)])
            ->assertRedirect('/panel/kubek-z-napisem#zdjecie')
            ->assertSessionHas('panel_status', 'Nowe zdjęcie kubka — klientki już na nim piszą');

        $first = Setting::find('mug_configurator_image')->value;
        $this->assertMatchesRegularExpression('#^mug-configurator/kubek-z-napisem-[a-z0-9]{6}\.webp$#', $first);
        Storage::disk('public')->assertExists($first);
        $this->assertSame('image/webp', Storage::disk('public')->mimeType($first));
        $this->get('/kubek-z-napisem')->assertSee(Storage::disk('public')->url($first), false);

        $this->post('/panel/kubek-z-napisem/zdjecie', ['photo' => UploadedFile::fake()->image('kubek-bok.png', 900, 900)]);
        $second = Setting::find('mug_configurator_image')->value;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->get('/panel/kubek-z-napisem')->assertSee('Przywróć domyślne zdjęcie');
        $this->delete('/panel/kubek-z-napisem/zdjecie')->assertRedirect('/panel/kubek-z-napisem#zdjecie');
        $this->assertSame(MugOptions::DEFAULT_PHOTO, Setting::find('mug_configurator_image')->value);
        Storage::disk('public')->assertMissing($second);

        $this->post('/panel/kubek-z-napisem/zdjecie', ['photo' => UploadedFile::fake()->create('napis.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrorsIn('zdjecie', ['photo' => 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP']);
    }
}
