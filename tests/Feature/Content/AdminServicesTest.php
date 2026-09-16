<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Modules\Content\Actions\AddServiceExample;
use App\Modules\Content\Enums\Service;
use App\Modules\Content\Models\ServiceExample;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminServicesTest extends TestCase
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

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/panel/uslugi')->assertRedirect('/panel/logowanie');
        $this->put('/panel/uslugi/apaszka/teksty')->assertRedirect('/panel/logowanie');
        $this->post('/panel/uslugi/apaszka/przed-i-po')->assertRedirect('/panel/logowanie');
        $this->delete('/panel/uslugi/przed-i-po/1')->assertRedirect('/panel/logowanie');
    }

    public function test_the_scarf_tab_opens_first_with_its_texts_prices_and_steps(): void
    {
        $response = $this->actingAs($this->owner)->get('/panel/uslugi');

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('admin.services.edit', 'apaszka')).'"\s+aria-current="page"#', $response->getContent());
        $response->assertOk()
            ->assertSee('>Usługi</a>', false)
            ->assertSee('href="'.route('content.scarf').'" target="_blank"', false)
            ->assertSeeInOrder(['Jeszcze nie ma par', 'Nowa para', 'Dodaj parę zdjęć'])
            ->assertSee("Apaszka babci\nnie musi leżeć\nw szafie</textarea>", false)
            ->assertSeeInOrder(['value="Opaska szeroka, pikowana"', 'value="119"', 'value="Zestaw: opaska i dwie scrunchies"', 'value="199"', 'placeholder="Nowa pozycja"'], false)
            ->assertSee('aria-label="Przesuń niżej: Opaska szeroka, pikowana"', false)
            ->assertSeeInOrder(['value="Wysyłasz zdjęcie tkaniny"', 'value="Szyję i odsyłam"', 'placeholder="Nowy krok"'], false)
            ->assertDontSee('value="Talerzyk deserowy 18 cm"', false);
    }

    public function test_the_imprint_tab_shows_its_own_settings_and_an_unknown_service_is_not_found(): void
    {
        $response = $this->actingAs($this->owner)->get('/panel/uslugi/odcisk');

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('admin.services.edit', 'odcisk')).'"\s+aria-current="page"#', $response->getContent());
        $response->assertOk()
            ->assertSee('value="Talerzyk deserowy 18 cm"', false)
            ->assertSee('value="Suszysz roślinę płasko"', false);

        $this->get('/panel/uslugi/kubek')->assertNotFound();
        $this->put('/panel/uslugi/kubek/teksty', ['heading' => 'Kubek'])->assertNotFound();
    }

    public function test_texts_are_saved_for_the_chosen_service_only(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/uslugi/odcisk/teksty', ['heading' => "Kwiat\r\nzostanie ", 'lead' => ' Przyślij roślinę. ', 'lead_2' => '', 'note' => 'Zwracam wpłatę.'])
            ->assertRedirect('/panel/uslugi/odcisk#teksty')
            ->assertSessionHas('panel_status', 'Teksty zapisane. Klienci już je widzą.');

        $this->assertSame("Kwiat\nzostanie", Setting::find('text_imprint_heading')->value);
        $this->assertSame('Przyślij roślinę.', Setting::find('text_imprint_lead')->value);
        $this->assertNull(Setting::find('text_imprint_lead_2')->value);
        $this->assertSame("Apaszka babci\nnie musi leżeć\nw szafie", Setting::find('text_scarf_heading')->value);

        $this->get('/odcisk-twojej-rosliny')->assertSee('Przyślij roślinę.')->assertSee('Zwracam wpłatę.')->assertDontSee('Roślina spala się w piecu');
    }

    public function test_a_too_long_text_is_explained_and_not_saved(): void
    {
        $this->actingAs($this->owner)
            ->followingRedirects()
            ->put('/panel/uslugi/apaszka/teksty', ['heading' => str_repeat('a', 121)])
            ->assertSee('Ten tekst zmieszczę do 120 znaków — skróć go o kilka słów')
            ->assertSee('Popraw zaznaczone pola, żeby zapisać.');

        $this->assertSame("Apaszka babci\nnie musi leżeć\nw szafie", Setting::find('text_scarf_heading')->value);
    }

    public function test_the_price_list_is_saved_without_removed_and_empty_rows(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/uslugi/apaszka/cennik', ['prices' => [
                ['label' => ' Opaska ', 'price' => '129,50', 'note' => ' szeroka '],
                ['label' => 'Scrunchie', 'price' => '69', 'note' => '', 'remove' => '1'],
                ['label' => 'Zestaw', 'price' => '199', 'note' => ''],
                ['label' => '', 'price' => '', 'note' => ''],
            ]])
            ->assertRedirect('/panel/uslugi/apaszka#cennik')
            ->assertSessionHas('panel_status', 'Cennik zapisany');

        $this->assertEquals([
            ['label' => 'Opaska', 'note' => 'szeroka', 'price_gross' => 12950],
            ['label' => 'Zestaw', 'note' => null, 'price_gross' => 19900],
        ], Setting::find('scarf_service_prices')->value);

        $this->get('/z-twojej-apaszki')->assertSeeInOrder(['Opaska', 'szeroka', '129,50 zł', 'Zestaw', '199,00 zł'])->assertDontSee('Scrunchie, rozmiar do wyboru');
    }

    public function test_a_price_without_a_name_and_a_bad_price_are_explained_on_their_row(): void
    {
        $this->actingAs($this->owner)
            ->followingRedirects()
            ->put('/panel/uslugi/apaszka/cennik', ['prices' => [
                ['label' => 'Opaska', 'price' => 'sto'],
                ['label' => '', 'price' => '50'],
            ]])
            ->assertSee('Wpisz cenę, np. 149 albo 149,50')
            ->assertSee('Wpisz nazwę pozycji do tej ceny')
            ->assertSee('value="sto"', false);

        $this->assertSame(11900, Setting::find('scarf_service_prices')->value[0]['price_gross']);
    }

    public function test_steps_are_moved_and_a_step_needs_a_name(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/uslugi/odcisk/kroki', ['move' => '1:up', 'steps' => [
                ['title' => 'Suszysz', 'text' => 'Dwa tygodnie.'],
                ['title' => 'Wysyłasz', 'text' => ''],
            ]])
            ->assertRedirect('/panel/uslugi/odcisk#kroki')
            ->assertSessionHas('panel_status', 'Kolejność zmieniona. Klienci już ją widzą.');

        $this->assertEquals([['title' => 'Wysyłasz', 'text' => null], ['title' => 'Suszysz', 'text' => 'Dwa tygodnie.']], Setting::find('imprint_steps')->value);

        $this->followingRedirects()
            ->put('/panel/uslugi/odcisk/kroki', ['steps' => [['title' => '', 'text' => 'Opis bez nazwy']]])
            ->assertSee('Nazwij ten krok, np. Przysyłasz tkaninę');
    }

    public function test_a_pair_is_saved_as_webp_without_exif_and_cut_to_the_same_shape(): void
    {
        $response = $this->actingAs($this->owner)->post('/panel/uslugi/apaszka/przed-i-po', [
            'before' => UploadedFile::fake()->image('IMG_4821.jpg', 3200, 2400),
            'after' => $this->phonePhoto(400, 300),
            'caption' => ' Apaszka babci z lat 70. ',
            'before_alt' => '',
            'after_alt' => 'Opaska w granatowe kwiaty',
        ]);

        $example = ServiceExample::sole();
        $response->assertRedirect('/panel/uslugi/apaszka#para-'.$example->id)->assertSessionHas('panel_status', 'Para dodana — widać ją na stronie');
        $this->assertSame([Service::Scarf, 1, 'Apaszka babci z lat 70.', null, 'Opaska w granatowe kwiaty'], [$example->service, $example->sort_order, $example->caption, $example->before_alt, $example->after_alt]);

        $before = $example->getFirstMedia('before');
        $after = $example->getFirstMedia('after');
        $this->assertStringStartsWith('apaszka-przed-', $before->file_name);
        $this->assertStringStartsWith('apaszka-po-', $after->file_name);
        $this->assertSame(['image/webp', 'image/webp'], [$before->mime_type, $after->mime_type]);
        $this->assertSame([2400, 1800], array_slice(getimagesize($before->getPath()), 0, 2));
        // The phone photo is turned upright once and saved again without its EXIF data.
        $this->assertSame([300, 400], array_slice(getimagesize($after->getPath()), 0, 2));
        $this->assertDoesNotMatchRegularExpression('/exif/i', (string) file_get_contents($after->getPath()));
        $this->assertSame([240, 300], array_slice(getimagesize($before->getPath('thumb')), 0, 2));
        $this->assertSame([240, 300], array_slice(getimagesize($after->getPath('thumb')), 0, 2));
        $this->assertSame([960, 1200], array_slice(getimagesize($before->getPath('card')), 0, 2));

        $this->get('/panel/uslugi')
            ->assertDontSee('Jeszcze nie ma par')
            ->assertSee('id="para-'.$example->id.'"', false)
            ->assertSee('src="'.$before->getUrl('thumb').'"', false)
            ->assertSee('value="Apaszka babci z lat 70."', false)
            ->assertDontSee('Przesuń niżej parę 1');

        $this->post('/panel/uslugi/apaszka/przed-i-po', ['before' => UploadedFile::fake()->image('a.jpg'), 'after' => UploadedFile::fake()->image('b.jpg')]);
        $this->assertSame([1, 2], ServiceExample::query()->ordered()->pluck('sort_order')->all());
    }

    public function test_a_pair_needs_both_photos_and_only_photos(): void
    {
        $pdf = UploadedFile::fake()->create('cennik.pdf', 100, 'application/pdf');

        $this->actingAs($this->owner)
            ->followingRedirects()
            ->post('/panel/uslugi/odcisk/przed-i-po', ['before' => $pdf, 'caption' => 'Bukiet Oli'])
            ->assertSee('Popraw zaznaczone pola, żeby dodać parę.')
            ->assertSee('Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP')
            ->assertSee('Wybierz zdjęcie „po”')
            ->assertSee('value="Bukiet Oli"', false);

        $this->post('/panel/uslugi/odcisk/przed-i-po', ['before' => UploadedFile::fake()->image('a.jpg')])
            ->assertRedirect('/panel/uslugi/odcisk#przed-i-po')
            ->assertSessionHasErrorsIn('przed-i-po', ['after' => 'Wybierz zdjęcie „po”']);

        $this->assertSame(0, ServiceExample::count());
    }

    public function test_the_caption_and_descriptions_are_changed_with_mistakes_under_that_pair(): void
    {
        $example = $this->pair(Service::Scarf);

        $this->actingAs($this->owner)
            ->put('/panel/uslugi/przed-i-po/'.$example->id, ['caption' => 'Nowy podpis', 'before_alt' => ' Apaszka w kwiaty ', 'after_alt' => ''])
            ->assertRedirect('/panel/uslugi/apaszka#para-'.$example->id)
            ->assertSessionHas('panel_status', 'Opis zapisany');

        $example->refresh();
        $this->assertSame(['Nowy podpis', 'Apaszka w kwiaty', null], [$example->caption, $example->before_alt, $example->after_alt]);

        $this->followingRedirects()
            ->put('/panel/uslugi/przed-i-po/'.$example->id, ['caption' => str_repeat('a', 161)])
            ->assertSee('Podpis zmieszczę do 160 znaków')
            ->assertSee('value="'.str_repeat('a', 161).'"', false);

        $this->put('/panel/uslugi/przed-i-po/'.$example->id, ['caption' => str_repeat('a', 161)])
            ->assertSessionHasErrorsIn('para-'.$example->id, 'caption');
        $this->assertSame('Nowy podpis', $example->fresh()->caption);
    }

    public function test_a_pair_moves_only_among_the_pairs_of_its_service(): void
    {
        [$first, $second, $third] = [$this->pair(Service::Scarf), $this->pair(Service::Scarf), $this->pair(Service::Scarf)];
        $imprint = $this->pair(Service::Imprint);
        // Pairs saved with the same number still swap.
        ServiceExample::query()->where('service', Service::Scarf)->update(['sort_order' => 1]);

        $this->actingAs($this->owner)
            ->post('/panel/uslugi/przed-i-po/'.$second->id.'/kolejnosc', ['kierunek' => 'gora'])
            ->assertRedirect('/panel/uslugi/apaszka#para-'.$second->id)
            ->assertSessionHas('panel_status', 'Kolejność zmieniona. Klienci już ją widzą.');
        $this->assertSame([$second->id, $first->id, $third->id], $this->order(Service::Scarf));

        $this->post('/panel/uslugi/przed-i-po/'.$third->id.'/kolejnosc', ['kierunek' => 'dol']);
        $this->assertSame([$second->id, $first->id, $third->id], $this->order(Service::Scarf));
        $this->assertSame(1, $imprint->fresh()->sort_order);

        $this->get('/panel/uslugi')
            ->assertSee('aria-label="Przesuń niżej parę 1"', false)
            ->assertDontSee('aria-label="Przesuń wyżej parę 1"', false)
            ->assertDontSee('aria-label="Przesuń niżej parę 3"', false);
    }

    public function test_a_pair_is_deleted_with_its_photos(): void
    {
        $example = $this->pair(Service::Imprint);
        $path = $example->getFirstMedia('before')->getPath();

        $this->actingAs($this->owner)
            ->delete('/panel/uslugi/przed-i-po/'.$example->id)
            ->assertRedirect('/panel/uslugi/odcisk#przed-i-po')
            ->assertSessionHas('panel_status', 'Para usunięta ze strony');

        $this->assertModelMissing($example);
        $this->assertFileDoesNotExist($path);
    }

    private function pair(Service $service): ServiceExample
    {
        return app(AddServiceExample::class)($service, UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), []);
    }

    /**
     * @return list<int>
     */
    private function order(Service $service): array
    {
        return ServiceExample::query()->where('service', $service)->ordered()->pluck('id')->all();
    }

    /**
     * A JPEG the way a phone saves it: pixels on their side and EXIF Orientation 6 saying how to turn them.
     */
    private function phonePhoto(int $width, int $height): UploadedFile
    {
        ob_start();
        imagejpeg(imagecreatetruecolor($width, $height));
        $jpeg = (string) ob_get_clean();

        $exif = "Exif\0\0".'MM'.pack('nN', 42, 8).pack('n', 1).pack('nnNnn', 0x0112, 3, 1, 6, 0).pack('N', 0);

        return UploadedFile::fake()->createWithContent('IMG_4822.jpg', "\xFF\xD8\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));
    }
}
