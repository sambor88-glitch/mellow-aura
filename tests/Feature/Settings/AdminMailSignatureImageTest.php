<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Support\SampleOrder;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\MailTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMailSignatureImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public', ['url' => 'http://localhost/storage']);
    }

    public function test_an_uploaded_picture_is_scaled_to_twice_its_box_and_laid_on_the_cream_of_the_mail(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/panel/maile/grafika', ['image' => $this->png(1200, 480)])
            ->assertRedirect('/panel/maile#grafika-podpisu')
            ->assertSessionHas('panel_status', 'Grafika wgrana. Jest już w każdym mailu do klientów.');

        $image = Setting::find(MailTexts::SIGNATURE_IMAGE)->value;
        $this->assertSame(200, $image['width']);
        $this->assertSame(80, $image['height']);
        $this->assertMatchesRegularExpression('#^mail/signature-[a-z0-9]{8}\.png$#', $image['path']);

        $file = imagecreatefromstring(Storage::disk('public')->get($image['path']));
        $this->assertSame([400, 160], [imagesx($file), imagesy($file)]);
        // The see-through corner is the mail card's cream now, the ink stays ink.
        $this->assertSame(0xFCF9F4, imagecolorat($file, 0, 0) & 0xFFFFFF);
        $this->assertSame(0x2F2620, imagecolorat($file, 200, 80) & 0xFFFFFF);

        $this->actingAs(User::factory()->create())
            ->get('/panel/maile')
            ->assertOk()
            ->assertSee('src="http://localhost/storage/'.$image['path'].'" width="200" height="80" alt="Grafika nad podpisem"', false)
            ->assertSee('Wgraj inną grafikę')
            ->assertSee('Zdejmij grafikę z maili');
    }

    public function test_a_new_picture_replaces_the_old_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/panel/maile/grafika', ['image' => $this->png(600, 600)]);
        $old = Setting::find(MailTexts::SIGNATURE_IMAGE)->value;
        // A square logo fills the height of the box.
        $this->assertSame([80, 80], [$old['width'], $old['height']]);

        $this->actingAs($user)->post('/panel/maile/grafika', ['image' => $this->png(300, 100)]);
        $new = Setting::find(MailTexts::SIGNATURE_IMAGE)->value;

        $this->assertNotSame($old['path'], $new['path']);
        Storage::disk('public')->assertMissing($old['path']);
        Storage::disk('public')->assertExists($new['path']);
        // A picture smaller than twice the box is not blown up.
        $this->assertSame([150, 50], [$new['width'], $new['height']]);
    }

    public function test_every_customer_mail_in_every_language_has_the_picture_above_the_signature(): void
    {
        $this->actingAs(User::factory()->create())->post('/panel/maile/grafika', ['image' => $this->png(1200, 480)]);
        $tag = '<img src="http://localhost/storage/'.Setting::find(MailTexts::SIGNATURE_IMAGE)->value['path'].'" width="200" height="80" alt=""';

        $rendered = 0;

        foreach (MailTexts::mails() as $key => $mail) {
            foreach (array_intersect($mail->locales, Locales::enabled()) as $locale) {
                $class = MailTexts::mailClass($key);
                $html = $class::sample($locale)->locale($locale)->render();

                $this->assertStringContainsString($tag, $html, $key.' ('.$locale.')');
                $this->assertMatchesRegularExpression('#'.preg_quote($tag, '#').'[^>]*>\s*<p style="margin: 6px 0 0;[^"]*">Kasia</p>#', $html, $key.' ('.$locale.')');
                $rendered++;
            }
        }

        // 18 customer mails, four of them with an English template too.
        $this->assertSame(22, $rendered);
    }

    public function test_without_a_picture_or_with_its_file_gone_the_signature_stays_as_it_was(): void
    {
        $html = (new OrderConfirmed(SampleOrder::make('pl')))->render();
        $this->assertStringNotContainsString('/storage/mail/', $html);
        $this->assertStringContainsString('<p style="margin: 18px 0 0; font-family: Georgia, \'Times New Roman\', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>', $html);

        Setting::create(['key' => MailTexts::SIGNATURE_IMAGE, 'value' => ['path' => 'mail/signature-gone1234.png', 'width' => 200, 'height' => 80]]);
        app(Settings::class)->refresh();

        $this->assertStringNotContainsString('/storage/mail/', (new OrderConfirmed(SampleOrder::make('pl')))->render());
        $this->actingAs(User::factory()->create())
            ->get('/panel/maile')
            ->assertOk()
            ->assertSee('Bez grafiki — maile mają sam podpis')
            ->assertDontSee('Zdejmij grafikę z maili');
    }

    public function test_a_file_that_is_not_a_picture_or_is_too_narrow_is_turned_away(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/panel/maile')
            ->post('/panel/maile/grafika', ['image' => UploadedFile::fake()->create('podpis.pdf', 40, 'application/pdf')])
            ->assertRedirect('/panel/maile#grafika-podpisu')
            ->assertSessionHasErrors(['image' => 'Tego pliku nie dodam — wybierz grafikę JPG, PNG albo WebP'], null, 'grafika-podpisu');

        $this->actingAs($user)
            ->post('/panel/maile/grafika', ['image' => $this->png(250, 100)])
            ->assertSessionHasErrors(['image' => 'Grafika jest za mała i w mailu wyjdzie nieostra — wgraj co najmniej 300 px szerokości'], null, 'grafika-podpisu');

        $this->actingAs($user)
            ->post('/panel/maile/grafika')
            ->assertSessionHasErrors(['image' => 'Wybierz grafikę z telefonu albo komputera'], null, 'grafika-podpisu');

        $this->assertNull(Setting::find(MailTexts::SIGNATURE_IMAGE));
        $this->assertSame([], Storage::disk('public')->allFiles());

        // The last mistake shows under the upload, the others went with their own redirects.
        $this->actingAs($user)->get('/panel/maile')->assertSee('<p role="alert" class="mt-1.5 text-[13px] text-error">Wybierz grafikę z telefonu albo komputera</p>', false);
    }

    public function test_taking_the_picture_off_deletes_its_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/panel/maile/grafika', ['image' => $this->png(1200, 480)]);
        $path = Setting::find(MailTexts::SIGNATURE_IMAGE)->value['path'];

        $this->actingAs($user)
            ->delete('/panel/maile/grafika')
            ->assertRedirect('/panel/maile#grafika-podpisu')
            ->assertSessionHas('panel_status', 'Grafika zdjęta. Maile mają znowu sam podpis.');

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(Setting::find(MailTexts::SIGNATURE_IMAGE)->value);
    }

    public function test_a_helper_account_cannot_change_the_picture(): void
    {
        $helper = User::factory()->helper()->create();

        $this->actingAs($helper)->post('/panel/maile/grafika', ['image' => $this->png(1200, 480)])->assertForbidden();
        $this->actingAs($helper)->delete('/panel/maile/grafika')->assertForbidden();
        $this->assertNull(Setting::find(MailTexts::SIGNATURE_IMAGE));
    }

    /**
     * A see-through PNG with a block of ink from its middle to its bottom-right quarter, like a scanned signature.
     */
    private function png(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, intdiv($width, 4), intdiv($height, 4), $width - 1, $height - 1, imagecolorallocate($image, 0x2F, 0x26, 0x20));

        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent('podpis.png', (string) ob_get_clean());
    }
}
