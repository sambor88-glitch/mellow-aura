<?php

namespace Tests\Feature\CustomOrders;

use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Mail\BriefConfirmed;
use App\Modules\CustomOrders\Mail\BriefReceived;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BriefFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_the_form_lists_the_kinds_and_a_service_page_opens_it_with_its_service_chosen(): void
    {
        $this->get('/zamowienia-indywidualne')
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSeeInOrder(['Imię', 'E-mail', 'Telefon', 'Co to ma być?', 'Kubki z napisem', 'Serwis obiadowy albo do kawy',
                'Forma dekoracyjna albo rzeźba', 'Opaska z Twojej apaszki', 'Odcisk Twojej rośliny', 'Coś innego',
                'Ile sztuk?', 'Na kiedy?', 'Budżet, jaki masz w głowie', 'Twój pomysł', 'Wybierz zdjęcia z telefonu albo komputera', 'do 6 zdjęć', 'Wyślij pomysł', 'polityce prywatności'])
            ->assertDontSee('selected>', false);

        $this->get('/z-twojej-apaszki')->assertSee('href="'.route('custom-orders.index', ['usluga' => 'apaszka']).'#formularz"', false);
        $this->get('/odcisk-twojej-rosliny')->assertSee('href="'.route('custom-orders.index', ['usluga' => 'odcisk']).'#formularz"', false);

        $this->get('/zamowienia-indywidualne?usluga=apaszka')->assertSee('<option value="scarf" selected>', false);
        $this->get('/zamowienia-indywidualne?usluga=odcisk')->assertSee('<option value="imprint" selected>', false);
        $this->get('/zamowienia-indywidualne?usluga=nieznana')->assertDontSee('selected>', false);
    }

    public function test_the_page_and_its_form_have_no_english_address_until_their_texts_are_in_english(): void
    {
        // MA-301: the page is written in Polish, so /en/ has no copy of it until MA-187.
        $this->get('/en/custom-orders')->assertNotFound();
        $this->post('/en/custom-orders', $this->form())->assertNotFound();
    }

    public function test_an_idea_with_photos_is_saved_and_mailed_to_kasia_with_the_photos_and_to_the_customer(): void
    {
        Mail::fake();
        $this->setting('contact_email', 'kasia@example.com');

        $this->post('/zamowienia-indywidualne', $this->form([
            'phone' => ' 600 100 200 ',
            'quantity' => '12',
            'needed_by' => 'na 14 lutego',
            'photos' => [UploadedFile::fake()->image('serwis.jpg', 4000, 3000), UploadedFile::fake()->image('kolor.png', 800, 1200)],
        ]))->assertRedirect(route('custom-orders.index').'#formularz');

        $brief = CustomOrderRequest::sole();
        $this->assertSame(['Ola', 'ola@example.com', '600 100 200', BriefKind::Tableware, '12', 'na 14 lutego', null, 'Serwis na 12 osób, szkliwo jak morze', CustomOrderStatus::New],
            [$brief->name, $brief->email, $brief->phone, $brief->kind, $brief->quantity, $brief->needed_by, $brief->budget, $brief->description, $brief->status]);

        // Shrunk to 2400 px and saved again as WebP on the private disk, never on the public one.
        $photos = $brief->getMedia('photos');
        $this->assertSame(['pomysl-1-1.webp', 'pomysl-1-2.webp'], $photos->pluck('file_name')->all());
        $this->assertSame(['local', 'local'], $photos->pluck('disk')->all());
        $this->assertSame(['image/webp', 'image/webp'], $photos->pluck('mime_type')->all());
        [$width, $height] = getimagesize($photos[0]->getPath());
        $this->assertSame([2400, 1800], [$width, $height]);
        $this->assertSame([], Storage::disk('public')->allFiles());

        Mail::assertQueued(BriefReceived::class, fn (BriefReceived $mail) => $mail->hasTo('kasia@example.com')
            && $mail->hasReplyTo('ola@example.com', 'Ola')
            && $mail->hasSubject('Zamówienie indywidualne: Serwis obiadowy albo do kawy — Ola')
            && count($mail->attachments()) === 2);
        Mail::queued(BriefReceived::class)->sole()
            ->assertHasAttachedData(Storage::disk('local')->get($photos[0]->getPathRelativeToRoot()), 'pomysl-1-1.webp', ['mime' => 'image/webp'])
            ->assertSeeInHtml('Zdjęcia od klienta są w załącznikach.');
        Mail::assertQueued(BriefConfirmed::class, fn (BriefConfirmed $mail) => $mail->hasTo('ola@example.com')
            && $mail->hasReplyTo('kasia@example.com'));

        $this->get('/zamowienia-indywidualne')->assertSee('Mam Twój pomysł. Potwierdzenie poszło na ola@example.com');
        $this->get('/zamowienia-indywidualne')->assertDontSee('Mam Twój pomysł');
    }

    public function test_an_idea_without_photos_is_enough(): void
    {
        Mail::fake();
        $this->setting('contact_email', 'kasia@example.com');

        $this->post('/zamowienia-indywidualne', $this->form(['kind' => 'other']))->assertRedirect();

        $this->assertSame(0, CustomOrderRequest::sole()->getMedia('photos')->count());
        Mail::assertQueued(BriefReceived::class, fn (BriefReceived $mail) => $mail->attachments() === []);
    }

    public function test_both_mails_show_the_idea_and_escape_what_the_customer_wrote(): void
    {
        $brief = CustomOrderRequest::query()->create([
            'name' => 'Ola <b>', 'email' => 'ola@example.com', 'phone' => '600 100 200', 'kind' => BriefKind::Scarf,
            'quantity' => '2', 'needed_by' => '<i>maj</i>', 'budget' => 'do 300 zł', 'description' => "Apaszka po babci\n<script>alert(1)</script>",
        ]);

        $toKasia = new BriefReceived($brief);
        $toKasia->assertSeeInOrderInHtml(['Opaska z Twojej apaszki', '2', '&lt;i&gt;maj&lt;/i&gt;', 'do 300 zł', 'Apaszka po babci', '600 100 200'], false);
        $toKasia->assertSeeInHtml('Ola &lt;b&gt;', false);
        $toKasia->assertDontSeeInHtml('<script>alert(1)</script>', false);
        $toKasia->assertDontSeeInHtml('Zdjęcia od klienta');
        $toKasia->assertSeeInText("Budżet: do 300 zł\n\nApaszka po babci\n<script>alert(1)</script>", false);

        $toCustomer = new BriefConfirmed($brief);
        $toCustomer->assertSeeInOrderInHtml(['Mam Twój pomysł', 'Opaska z Twojej apaszki', 'Twój opis', 'Apaszka po babci', 'Kasia'], false);
        $toCustomer->assertDontSeeInHtml('<script>alert(1)</script>', false);
        $toCustomer->assertSeeInOrderInText(['Co to ma być: Opaska z Twojej apaszki', 'Ile sztuk: 2', 'Na kiedy: <i>maj</i>', 'TWÓJ OPIS']);
    }

    public function test_the_form_says_what_is_missing_and_keeps_what_was_written(): void
    {
        Mail::fake();

        $this->followingRedirects()
            ->post('/zamowienia-indywidualne', $this->form(['name' => '', 'email' => 'ola.example.com', 'kind' => '', 'description' => '', 'budget' => 'około 500 zł', 'photos_chosen' => '2']))
            ->assertSee('Wpisz imię — żebym wiedziała, jak się do Ciebie zwracać')
            ->assertSee('Adres e-mail bez małpy — sprawdź, czy nie uciekła')
            ->assertSee('Wybierz, co to ma być — albo „Coś innego”', false)
            ->assertSee('Opisz pomysł choć w kilku zdaniach — resztę dopytam')
            ->assertSee('Zdjęcia wybierz jeszcze raz — po błędzie przeglądarka ich nie pamięta.')
            ->assertSee('value="ola.example.com"', false)
            ->assertSee('value="około 500 zł"', false);

        $this->followingRedirects()
            ->post('/zamowienia-indywidualne', $this->form(['kind' => 'vase']))
            ->assertSee('Wybierz, co to ma być, z listy')
            ->assertSee('<option value="tableware"', false);

        $this->assertDatabaseCount('custom_order_requests', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_only_up_to_six_photos_of_jpg_png_or_webp_go_through(): void
    {
        Mail::fake();

        $this->followingRedirects()
            ->post('/zamowienia-indywidualne', $this->form(['photos' => [UploadedFile::fake()->create('wycena.pdf', 100, 'application/pdf')]]))
            ->assertSee('Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP');

        $this->followingRedirects()
            ->post('/zamowienia-indywidualne', $this->form(['photos' => array_map(fn (int $i) => UploadedFile::fake()->image("$i.jpg", 10, 10), range(1, 7))]))
            ->assertSee('Wyślę do 6 zdjęć — wybierz te, na których najlepiej widać pomysł');

        $this->followingRedirects()
            ->post('/zamowienia-indywidualne', $this->form(['photos' => [UploadedFile::fake()->image('duze.jpg', 10, 10)->size(21 * 1024)]]))
            ->assertSee('Zdjęcie jest za duże — jedno do 20 MB');

        $this->assertDatabaseCount('custom_order_requests', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_a_bot_that_fills_the_hidden_field_saves_and_sends_nothing(): void
    {
        Mail::fake();

        $this->post('/zamowienia-indywidualne', $this->form(['website' => 'https://spam.example']))
            ->assertRedirect(route('custom-orders.index').'#formularz');

        $this->assertDatabaseCount('custom_order_requests', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_one_address_sends_up_to_five_ideas_an_hour(): void
    {
        Mail::fake();
        $this->setting('contact_phone', '+48 600 100 200');

        foreach (range(1, 5) as $attempt) {
            $this->post('/zamowienia-indywidualne', $this->form());
        }

        $this->followingRedirects()
            ->post('/zamowienia-indywidualne', $this->form(['description' => 'Szósty pomysł']))
            ->assertSee('Mam już od Ciebie kilka wiadomości i na wszystkie odpiszę. Kolejną wyślesz za godzinę, a jeśli to pilne, napisz na WhatsAppie +48 600 100 200.')
            ->assertSee('Szósty pomysł');

        $this->assertDatabaseCount('custom_order_requests', 5);
    }

    public function test_without_an_e_mail_in_the_panel_the_idea_is_saved_and_the_customer_still_gets_her_copy(): void
    {
        Exceptions::fake();
        Mail::fake();
        $this->setting('contact_email', null);

        $this->post('/zamowienia-indywidualne', $this->form())->assertRedirect();

        $this->assertDatabaseCount('custom_order_requests', 1);
        Mail::assertNotQueued(BriefReceived::class);
        Mail::assertQueued(BriefConfirmed::class);
        Exceptions::assertReported(RuntimeException::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Ola',
            'email' => 'ola@example.com',
            'phone' => '',
            'kind' => 'tableware',
            'quantity' => '',
            'needed_by' => '',
            'budget' => '',
            'description' => 'Serwis na 12 osób, szkliwo jak morze',
            'website' => '',
            ...$overrides,
        ];
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->app->make(Settings::class)->refresh();
    }
}
